<!DOCTYPE html>
<html lang="fa">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css"> <!-- Link to the external stylesheet -->
    <title>پرداخت موفق</title>
    <style>
        body {
            background-color: #ffffff;
            color: #000000;
            font-family: 'Iranian Sans', 'Tahoma', sans-serif;
            text-align: center;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background-color: #ffffff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-radius: 10px;
            max-width: 400px;
        }

        h1 {
            color: #008000;
            margin-bottom: 10px;
        }

        p {
            margin-top: 0;
            margin-bottom: 20px;
        }

        button {
            background-color: #4CAF50;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>پرداخت موفق</h1>
        <p>پرداخت شما با موفقیت انجام شد.</p>
        <p>کد رهگیری پرداخت شما:</p>
        <p>{{$referenceId}}</p>
        <button onclick="returnToApp()">بازگشت به اپلیکیشن</button>
    </div>

    <script>
        function returnToApp() {}
    </script>
</body>

</html>