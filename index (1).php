<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$pass = "";
$db = "clinic_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

function clean($data) {
    return htmlspecialchars(trim($data));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["register_patient"])) {
        $name = clean($_POST["name"]);
        $email = clean($_POST["email"]);
        $phone = clean($_POST["phone"]);
        $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $age = (int) $_POST["age"];
        $gender = clean($_POST["gender"]);

        $check = $conn->prepare("SELECT patient_id FROM patients WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "Email already registered.";
        } else {
            $stmt = $conn->prepare("INSERT INTO patients (name, email, phone, password, age, gender) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssis", $name, $email, $phone, $password, $age, $gender);

            if ($stmt->execute()) {
                $message = "Registration Successful.";
            } else {
                $message = "Registration Failed: " . $stmt->error;
            }

            $stmt->close();
        }

        $check->close();
    }

    if (isset($_POST["patient_login"])) {
        $email = clean($_POST["login_email"]);
        $password = $_POST["login_password"];

        $stmt = $conn->prepare("SELECT patient_id, name, password FROM patients WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {
                $_SESSION["patient_id"] = $user["patient_id"];
                $_SESSION["patient_name"] = $user["name"];
                $message = "Login Successful. Welcome " . $user["name"] . ".";
            } else {
                $message = "Invalid Password.";
            }
        } else {
            $message = "Email not found.";
        }

        $stmt->close();
    }

    if (isset($_POST["book_appointment"])) {
        if (!isset($_SESSION["patient_id"])) {
            $message = "Please login first to book an appointment.";
        } else {
            $patient_id = (int) $_SESSION["patient_id"];
            $doctor_id = (int) $_POST["doctor_id"];
            $appointment_date = clean($_POST["appointment_date"]);
            $appointment_time = clean($_POST["appointment_time"]);

            $check = $conn->prepare("SELECT doctor_id FROM doctors WHERE doctor_id = ?");
            $check->bind_param("i", $doctor_id);
            $check->execute();
            $doctor_result = $check->get_result();

            if ($doctor_result->num_rows === 0) {
                $message = "Doctor ID does not exist.";
            } else {
                $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, 'Pending')");
                $stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $appointment_time);

                if ($stmt->execute()) {
                    $message = "Appointment booked successfully.";
                } else {
                    $message = "Appointment booking failed: " . $stmt->error;
                }

                $stmt->close();
            }

            $check->close();
        }
    }

    if (isset($_POST["add_prescription"])) {
        $app_id = (int) $_POST["app_id"];
        $doctor_id = (int) $_POST["doctor_id"];
        $patient_id = (int) $_POST["patient_id"];
        $medicines = clean($_POST["medicines"]);
        $notes = clean($_POST["notes"]);

        $check_patient = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
        $check_patient->bind_param("i", $patient_id);
        $check_patient->execute();
        $patient_result = $check_patient->get_result();

        $check_app = $conn->prepare("SELECT app_id FROM appointments WHERE app_id = ?");
        $check_app->bind_param("i", $app_id);
        $check_app->execute();
        $app_result = $check_app->get_result();

        if ($patient_result->num_rows === 0) {
            $message = "Patient ID does not exist.";
        } elseif ($app_result->num_rows === 0) {
            $message = "Appointment ID does not exist.";
        } else {
            $stmt = $conn->prepare("INSERT INTO prescriptions (app_id, doctor_id, patient_id, medicines, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $app_id, $doctor_id, $patient_id, $medicines, $notes);

            if ($stmt->execute()) {
                $message = "Prescription saved successfully.";
            } else {
                $message = "Prescription failed: " . $stmt->error;
            }

            $stmt->close();
        }

        $check_patient->close();
        $check_app->close();
    }

    if (isset($_POST["add_bill"])) {
        $patient_id = (int) $_POST["bill_patient_id"];
        $app_id = (int) $_POST["bill_app_id"];
        $amount = (float) $_POST["amount"];
        $billing_date = clean($_POST["billing_date"]);
        $payment_status = clean($_POST["payment_status"]);

        $check_patient = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
        $check_patient->bind_param("i", $patient_id);
        $check_patient->execute();
        $patient_result = $check_patient->get_result();

        $check_app = $conn->prepare("SELECT app_id FROM appointments WHERE app_id = ?");
        $check_app->bind_param("i", $app_id);
        $check_app->execute();
        $app_result = $check_app->get_result();

        if ($patient_result->num_rows === 0) {
            $message = "Cannot generate bill: Patient ID does not exist.";
        } elseif ($app_result->num_rows === 0) {
            $message = "Cannot generate bill: Appointment ID does not exist.";
        } else {
            $stmt = $conn->prepare("INSERT INTO billing (patient_id, app_id, amount, billing_date, payment_status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iidss", $patient_id, $app_id, $amount, $billing_date, $payment_status);

            if ($stmt->execute()) {
                $message = "Bill generated successfully.";
            } else {
                $message = "Bill generation failed: " . $stmt->error;
            }

            $stmt->close();
        }

        $check_patient->close();
        $check_app->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --hospital-green: #2ecc71;
            --hospital-dark: #27ae60;
            --hospital-light: #e8f8f0;
            --teal-accent: #26c6da;
            --soft-green: #f0fdf7;
            --card-shadow: rgba(46, 204, 113, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', -apple-system, sans-serif;
            background: linear-gradient(135deg, #f0fdf7 0%, #e8f8f0 50%, #f0fdf9 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }

        .clinic-brand {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 9999;
            background: rgba(255, 255, 255, 0.92);
            color: #27ae60;
            padding: 10px 18px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.18);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clinic-brand i {
            color: #2ecc71;
        }

        .hero-section {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: #fff;
            padding: 70px 30px;
            text-align: center;
            border-radius: 30px;
            margin-bottom: 50px;
            box-shadow: 0 15px 50px rgba(46, 204, 113, 0.3);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 400px;
            height: 400px;
            background: rgba(38, 198, 218, 0.15);
            border-radius: 50%;
        }

        .hero-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
            color: #fff;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .hero-section h1 {
            font-weight: 800;
            font-size: 3rem;
            margin-bottom: 15px;
            letter-spacing: -1px;
        }

        .hero-section p {
            font-size: 1.25rem;
            opacity: 0.95;
            font-weight: 600;
        }

        .container {
            max-width: 1250px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 25px;
            background: #fff;
            box-shadow: 0 10px 40px rgba(46, 204, 113, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
            height: 100%;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #2ecc71, #26c6da, #27ae60);
        }

        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(46, 204, 113, 0.25);
        }

        .card-header {
            background: linear-gradient(135deg, #f0fdf7 0%, #fff 100%);
            padding: 25px 30px;
            border-bottom: 2px solid #e0f5ea;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .card-icon {
            font-size: 2rem;
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .card-icon.success {
            background: linear-gradient(135deg, #26c6da 0%, #2ecc71 100%);
            box-shadow: 0 4px 15px rgba(38, 198, 218, 0.3);
        }

        .card-icon.warning {
            background: linear-gradient(135deg, #ffe66d 0%, #2ecc71 100%);
            box-shadow: 0 4px 15px rgba(255, 230, 109, 0.3);
        }

        .card-icon.info {
            background: linear-gradient(135deg, #5f27cd 0%, #26c6da 100%);
            box-shadow: 0 4px 15px rgba(95, 39, 205, 0.3);
        }

        .card-icon.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #dc3545 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .card-header h4 {
            color: #333;
            font-weight: 700;
            font-size: 1.35rem;
            margin: 0;
        }

        .card-body {
            padding: 30px;
        }

        .form-control, .form-select {
            border: 2px solid #e8e8e8;
            border-radius: 15px;
            padding: 14px 18px;
            font-size: 15px;
            margin-bottom: 18px;
            transition: all 0.3s ease;
            background: #fafafa;
            font-family: 'Nunito', sans-serif;
        }

        .form-control:hover, .form-select:hover {
            border-color: #d0f0e0;
            background: #fff;
        }

        .form-control:focus, .form-select:focus {
            border-color: #2ecc71;
            box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.15);
            background: #fff;
        }

        .form-control::placeholder {
            color: #bbb;
        }

        textarea.form-control {
            min-height: 90px;
            resize: vertical;
        }

        .btn {
            border-radius: 15px;
            padding: 14px 30px;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            font-family: 'Nunito', sans-serif;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #25c065 0%, #209d55 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #26c6da 0%, #2ecc71 100%);
            color: #fff;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #22b4c8 0%, #27ae60 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffe66d 0%, #ffd54f 100%);
            color: #333;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #ffe055 0%, #ffcc35 100%);
        }

        .btn-info {
            background: linear-gradient(135deg, #5f27cd 0%, #4ecdc4 100%);
            color: #fff;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #501bc4 0%, #44c4bb 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #dc3545 100%);
            color: #fff;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #ff5252 0%, #c82333 100%);
        }

        .alert {
            border-radius: 20px;
            padding: 18px 25px;
            margin-bottom: 40px;
            border: none;
            box-shadow: 0 8px 30px rgba(46, 204, 113, 0.2);
            font-weight: 700;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-info {
            background: linear-gradient(135deg, #f0fdf7 0%, #e0f5ea 100%);
            color: #2ecc71;
            border: 2px solid #d0f0e0;
        }

        .floating-symbols {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            opacity: 0.03;
        }

        .plus-symbol {
            position: absolute;
            font-size: 2rem;
            color: #2ecc71;
        }

        @media (max-width: 992px) {
            .hero-section h1 {
                font-size: 2.2rem;
            }

            .hero-section p {
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 1.8rem;
            }

            .hero-section p {
                font-size: 0.95rem;
            }

            .hero-icon {
                font-size: 3rem;
            }

            .btn {
                width: 100%;
            }

            .card-body {
                padding: 20px;
            }

            .card-header {
                padding: 20px;
            }

            body {
                padding: 10px;
            }

            .clinic-brand {
                top: 12px;
                left: 12px;
                font-size: 0.9rem;
                padding: 8px 14px;
            }
        }
    </style>
</head>
<body>
    <div class="clinic-brand">
        <i class="fas fa-hospital"></i> Healthcare
    </div>

    <div class="floating-symbols">
        <div class="plus-symbol" style="top: 10%; left: 5%;"><i class="fas fa-plus"></i></div>
        <div class="plus-symbol" style="top: 20%; left: 85%;"><i class="fas fa-plus"></i></div>
        <div class="plus-symbol" style="top: 40%; left: 15%;"><i class="fas fa-plus"></i></div>
        <div class="plus-symbol" style="top: 60%; left: 75%;"><i class="fas fa-plus"></i></div>
        <div class="plus-symbol" style="top: 75%; left: 40%;"><i class="fas fa-plus"></i></div>
        <div class="plus-symbol" style="top: 85%; left: 90%;"><i class="fas fa-plus"></i></div>
    </div>

    <div class="container">
        <div class="hero-section">
            <div class="hero-icon">
                <i class="fas fa-plus"></i>
            </div>
            <h1>Welcome to Your Clinic</h1>
            <p>We're here to care for you and your family</p>
        </div>

        <?php if ($message != "") { ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php } ?>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h4>Patient Registration</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="text" name="name" class="form-control" placeholder="👤 Full Name" required>
                            <input type="email" name="email" class="form-control" placeholder="✉️ Email Address" required>
                            <input type="text" name="phone" class="form-control" placeholder="📱 Phone Number" required>
                            <input type="password" name="password" class="form-control" placeholder="🔒 Password" required>
                            <input type="number" name="age" class="form-control" placeholder="🎂 Age" required>
                            <select name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <button type="submit" name="register_patient" class="btn btn-primary w-100">
                                <i class="fas fa-user-check"></i> Register Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon success">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <h4>Patient Login</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="email" name="login_email" class="form-control" placeholder="✉️ Email Address" required>
                            <input type="password" name="login_password" class="form-control" placeholder="🔒 Password" required>
                            <button type="submit" name="patient_login" class="btn btn-success w-100">
                                <i class="fas fa-login"></i> Login Here
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon warning">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h4>Book Appointment</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="number" name="doctor_id" class="form-control" placeholder="👨‍⚕️ Doctor ID" required>
                            <input type="date" name="appointment_date" class="form-control" required>
                            <input type="time" name="appointment_time" class="form-control" required>
                            <button type="submit" name="book_appointment" class="btn btn-warning w-100">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon info">
                            <i class="fas fa-pills"></i>
                        </div>
                        <h4>Add Prescription</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="number" name="app_id" class="form-control" placeholder="📋 Appointment ID" required>
                            <input type="number" name="doctor_id" class="form-control" placeholder="👨‍⚕️ Doctor ID" required>
                            <input type="number" name="patient_id" class="form-control" placeholder="👤 Patient ID" required>
                            <textarea name="medicines" class="form-control" placeholder="💊 Medicines (List all)" required></textarea>
                            <textarea name="notes" class="form-control" placeholder="📝 Notes (Optional)"></textarea>
                            <button type="submit" name="add_prescription" class="btn btn-info w-100">
                                <i class="fas fa-save"></i> Save Prescription
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon danger">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h4>Generate Bill</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <input type="number" name="bill_patient_id" class="form-control" placeholder="👤 Patient ID" required>
                            </div>
                            <div class="col-md-4">
                                <input type="number" name="bill_app_id" class="form-control" placeholder="📋 Appointment ID" required>
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="💰 Amount" required>
                            </div>
                            <div class="col-md-6">
                                <input type="date" name="billing_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <select name="payment_status" class="form-select" required>
                                    <option value="Unpaid">Unpaid</option>
                                    <option value="Paid">Paid</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_bill" class="btn btn-danger w-100">
                                    <i class="fas fa-print"></i> Generate Bill
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 50px; color: #2ecc71; font-weight: 700;">
            <p style="font-size: 1.1rem;">
                <i class="fas fa-plus"></i> Thank you for choosing our clinic! <i class="fas fa-plus"></i>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>