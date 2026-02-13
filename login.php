<?php
// login.php - (Royal Premium Login V2.0)
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($password == $user['password'] || password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "كلمة المرور غير صحيحة";
        }
    } else {
        $error = "المستخدم غير موجود";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | Arab Eagles</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --gold-glow: rgba(212, 175, 55, 0.3);
            --bg-dark: #050505;
        }

        body, html {
            margin: 0; padding: 0; height: 100%;
            font-family: 'Cairo', sans-serif;
            background: var(--bg-dark);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Particles Background Container */
        #particles-js {
            position: absolute; width: 100%; height: 100%;
            background-color: var(--bg-dark);
            z-index: 1;
        }

        .login-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .royal-card {
            background: rgba(20, 20, 20, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.8);
            text-align: center;
            transition: 0.5s;
        }

        .royal-card:hover {
            border-color: var(--gold);
            box-shadow: 0 0 25px var(--gold-glow);
        }

        .logo-area { margin-bottom: 30px; }
        .logo-text {
            color: var(--gold);
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0;
            text-shadow: 0 0 15px var(--gold-glow);
        }
        .logo-sub { color: #888; font-size: 0.9rem; margin-top: 5px; display: block; }

        .input-group { margin-bottom: 20px; position: relative; }
        
        input {
            width: 100%;
            padding: 15px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
            border-radius: 10px;
            color: #fff;
            font-family: 'Cairo';
            font-size: 1rem;
            box-sizing: border-box;
            transition: 0.3s;
            text-align: center;
        }

        input:focus {
            border-color: var(--gold);
            outline: none;
            background: rgba(0,0,0,0.8);
            box-shadow: 0 0 10px var(--gold-glow);
        }

        .btn-royal {
            background: linear-gradient(135deg, var(--gold), #b8860b);
            color: #000;
            font-weight: 700;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1rem;
            font-family: 'Cairo';
            transition: 0.4s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .btn-royal:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
            filter: brightness(1.1);
        }

        .error-msg {
            background: rgba(192, 57, 43, 0.1);
            border: 1px solid #c0392b;
            color: #ff6b6b;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .floating { animation: float 4s ease-in-out infinite; }

    </style>
</head>
<body>

    <div id="particles-js"></div>

    <div class="login-container">
        <div class="royal-card floating">
            <div class="logo-area">
                <span class="logo-text">ARAB EAGLES</span>
                <span class="logo-sub">Smart Management System</span>
            </div>

            <?php if($error): ?>
                <div class="error-msg">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <input type="text" name="username" placeholder="اسم المستخدم" required autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="كلمة المرور" required>
                </div>
                <button type="submit" class="btn-royal">دخول للنظام</button>
            </form>
            
            <p style="color: #444; font-size: 0.7rem; margin-top: 30px;">
                V3.0 &copy; <?php echo date('Y'); ?> Arab Eagles Portfolio
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 80, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#d4af37" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.5, "random": true },
                "size": { "value": 3, "random": true },
                "line_linked": { "enable": true, "distance": 150, "color": "#d4af37", "opacity": 0.2, "width": 1 },
                "move": { "enable": true, "speed": 2, "direction": "none", "random": true, "straight": false, "out_mode": "out", "bounce": false }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" }, "resize": true },
                "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } } }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>