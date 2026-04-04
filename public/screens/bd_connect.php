

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>example.local</title>
    <link rel="stylesheet" href="public/css/index.css">
    <link rel="stylesheet" href="public/css/header.css">
    <link rel="stylesheet" href="public/css/php_test.css">
    <script src="public/components/header.js"></script>
</head>
<body>
    <?php
    $host = '127.127.126.25';
    $user = 'root';
    $pass = '';
    $base = 'test_sql';
    
    $conn = mysqli_connect($host, $user, $pass, $base);
    $query = "SELECT * FROM `first`";
    $result = mysqli_query($conn, $query);
    $data = $result -> fetch_all(MYSQLI_ASSOC);
    print_r($data[0]);
    ?>

    <my-header></my-header>
    <div class="main-content">
        <div class="poem-title"><?php  ?></div>
        <div class="poem-author"></div>
        <div class="poem-text"></div>
        <div class="poem-rating"></div>
    </div>
</body>
</html>