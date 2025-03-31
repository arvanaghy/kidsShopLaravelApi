<!DOCTYPE html>
<html lang="fa"> 

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css"> 
    <title>پرداخت ناموفق</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Iranian+Sans:wght@300&display=swap" rel="stylesheet"> 
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
            color: #ff0000;
            margin-bottom: 10px;
        }

        p {
            margin-top: 0;
            margin-bottom: 20px;
        }

        button {
            background-color: #e74c3c;
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
        <h1>پرداخت ناموفق</h1> 
        <p>پرداخت شما ناموفق بود. لطفاً مجدداً تلاش کنید یا با پشتیبانی تماس بگیرید.</p> 
        <p>{{$exception}}</p>
        <button onclick="returnToApp()">بازگشت به اپلیکیشن</button> 
    </div>

    <script>
        function returnToApp() {}
    </script>
</body>

</html>