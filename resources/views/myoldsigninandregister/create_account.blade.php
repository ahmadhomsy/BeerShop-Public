<html>
<head>
  <title>createAccount</title>
  <meta catset="utf-8">
  <meta name="description" content="">
  <link rel="stylesheet" href="../css/create_account.css">
  <style></style>
  <script></script>
</head>
<body>
  <div class="creat_account">
    <div class="container">
      <div class="forms-cont">
        <div class="box">
          <p>creat new account</p>
          <img src="../images/kkkk.jpg">
        </div>
        <hr class="h">
        <div class="maincomponent a">
          <div class="slide">
            <div class="a seller">seller</div>
            <div class="a buyer active">buyer</div>
            <div class="a admin">admin</div>
          </div>
        </div>
        <div class="sellerComp">
          <form name="creat_seller" action="../pages_reply/create_account_reply.php" method="post" >
            <br>
            &nbsp;&nbsp;&nbsp;<label>S name :</label>
            <input name="name" class="area" type="text" required placeholder="Enter your name">
            <br>
            &nbsp;&nbsp;&nbsp;<label>username :</label>
            <input name="username" class="area" type="text" required placeholder="Enter your username">
            <br>
            &nbsp;&nbsp;&nbsp;<label>email :</label>
            <input name="email" class="area" type="email" required placeholder="Enter your email">
            <br>
            &nbsp;&nbsp;&nbsp;<label>password :</label>
            <input name="password" class="area" type="password" required placeholder="Enter your password">
            &nbsp;&nbsp;&nbsp;<label>confirm pass :</label>
            <input name="passwordconf" class="area" type="password" required placeholder="Renter your password">
            <br>
            &nbsp;&nbsp;&nbsp;<label>category part :</label>
            <input name="category" class="area" type="text" required placeholder="Enter your category">
            <br>
            &nbsp;&nbsp;&nbsp;<label>country :</label>
            <input name="country" class="area" type="text" required placeholder="Enter your country">
            <br>
            <input class="button" type="submit" value="create">
            <input name="role"  type="hidden" value="seller">
          </form>
        </div>
        <div class="buyerComp show">
          <form name="creat_buyer" action="../pages_reply/create_account_reply.php" method="post" >
            <br>
            &nbsp;&nbsp;&nbsp;<label>B name :</label>
            <input name="name" class="area" type="text" required placeholder="Enter your name">
            <br>
            &nbsp;&nbsp;&nbsp;<label>username :</label>
            <input name="username" class="area" type="text" required placeholder="Enter your username">
            <br>
            &nbsp;&nbsp;&nbsp;<label>email :</label>
            <input name="email" class="area" type="email" required placeholder="Enter your email">
            <br>
            &nbsp;&nbsp;&nbsp;<label>password :</label>
            <input name="password" class="area" type="password" required placeholder="Enter your password">
            &nbsp;&nbsp;&nbsp;<label>confirm pass :</label>
            <input name="passwordconf" class="area" type="password" required placeholder="Enter your password">
            <br>
            <input class="button" type="submit" value="create">
            <input name="role"  type="hidden" value="buyer">
          </form>
        </div>
        <div class="adminComp">
          <form name="creat_admin" action="../pages_reply/create_account_reply.php" method="post" >
            <br>
            &nbsp;&nbsp;&nbsp;<label>A name :</label>
            <input name="name" class="area" type="text" required placeholder="Enter your name">
            <br>
            &nbsp;&nbsp;&nbsp;<label>username :</label>
            <input name="username" class="area" type="text" required placeholder="Enter your username">
            <br>
            &nbsp;&nbsp;&nbsp;<label>email :</label>
            <input name="email" class="area" type="email" required placeholder="Enter your email">
            <br>
            &nbsp;&nbsp;&nbsp;<label>password :</label>
            <input name="password" class="area" type="password" required placeholder="Enter your password">
            &nbsp;&nbsp;&nbsp;<label>confirm pass :</label>
            <input name="passwordconf" class="area" type="password" required placeholder="Enter your password">
            <br>
            <input class="button" type="submit" value="create">
            <input name="role"  type="hidden" value="admin">
          </form>
        </div>
        <a class="f_p" href="./sign_in.html">go back</a>
      </div>
    </div>
  </div>

<script src="../js/create_account.js"></script>
</body>
</html>