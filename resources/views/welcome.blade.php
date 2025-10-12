<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesma API</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #0d0d0d;
            font-family: 'Courier New', monospace;
            overflow: hidden;
        }
        .container {
            text-align: center;
            color: #00ff00;
            text-shadow: 0 0 10px #00ff00, 0 0 20px #00ff00;
            position: relative;
            z-index: 1;
        }
        h1 {
            font-size: 3em;
            margin: 0;
            animation: glitch 1s linear infinite;
        }
        p {
            font-size: 1.5em;
            margin-top: 20px;
            opacity: 0;
            animation: fadeIn 2s ease-in forwards;
            animation-delay: 1s;
        }
        .flash-text {
            animation: flashColor 1.5s linear infinite, fadeIn 2s ease-in forwards;
        }
        .scanline {
            position: absolute;
            top: 0;
            width: 100%;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
            animation: scan 4s linear infinite;
        }
        .bg-matrix {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.1;
        }
        @keyframes glitch {
            2%, 64% {
                transform: translate(2px, 0) skew(0deg);
            }
            4%, 60% {
                transform: translate(-2px, 0) skew(0deg);
            }
            62% {
                transform: translate(0, 0) skew(5deg);
            }
        }
        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }
        @keyframes scan {
            0% {
                transform: translateY(-100%);
            }
            100% {
                transform: translateY(100vh);
            }
        }
        @keyframes flashColor {
            0% {
                color: #00ff00;
                text-shadow: 0 0 10px #00ff00, 0 0 20px #00ff00;
            }
            33% {
                color: #ff00ff;
                text-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff;
            }
            66% {
                color: #00ffff;
                text-shadow: 0 0 10px #00ffff, 0 0 20px #00ffff;
            }
            100% {
                color: #00ff00;
                text-shadow: 0 0 10px #00ff00, 0 0 20px #00ff00;
            }
        }
    </style>
</head>
<body>
    <canvas class="bg-matrix"></canvas>
    <div class="scanline"></div>
    <div class="container">
        <h1>Hesma API</h1>
        <p>Thanks for using Hesma API</p>
        <p class="flash-text">Have fun with Ilyas (Elyas | Elijah) Beyefendi! :))</p>
    </div>
    <script>
        const canvas = document.querySelector('.bg-matrix');
        const ctx = canvas.getContext('2d');
        canvas.height = window.innerHeight;
        canvas.width = window.innerWidth;
        const chars = '01IlyasElyasBeyefendi';
        const fontSize = 14;
        const columns = canvas.width / fontSize;
        const drops = [];
        for (let x = 0; x < columns; x++) drops[x] = 1;
        function draw() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#00ff00';
            ctx.font = fontSize + 'px monospace';
            for (let i = 0; i < drops.length; i++) {
                const text = chars.charAt(Math.floor(Math.random() * chars.length));
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975)
                    drops[i] = 0;
                drops[i]++;
            }
        }
        setInterval(draw, 33);
        window.addEventListener('resize', () => {
            canvas.height = window.innerHeight;
            canvas.width = window.innerWidth;
        });
    </script>
</body>
</html>
