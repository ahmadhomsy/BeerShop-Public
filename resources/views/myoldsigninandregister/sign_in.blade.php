<html>
    <head>
        <title>signIn</title>
        <meta catset="utf-8">
        <meta name="description" content="wellcome">
        <link rel="stylesheet" href="../css/sign_in.css">
    </head>
    <body>
        <div class="card">
            <div class="box">
                <p>Login</p>
                <img src="../images/kkkk.jpg">
            </div>
            <hr class="h">
            <form method="get" action="/login">
                @csrf
                <br>
                <br>
                &nbsp;&nbsp;&nbsp;<label for="name">Email or Username :</label>
                <input name="name" id="name" class="area" type="text" autofocus  placeholder="Inter your email or username">
                <span class="text-danger">
                    @error('name')
                       {{$massage}}
                    @enderror
                 </span>
                <br>
                <br>
                &nbsp;&nbsp;&nbsp;<label for="password">Password :</label>
                <input name="password" id="password" class="area" type="password"  placeholder="Inter your password">
                <span class="text-danger">
                    @error('name')
                       {{$massage}}
                    @enderror
                 </span>
                <br>
                <input class="button" type="submit" value="sign in">
            </form>
            <a class="c_a" href="./create_account.html">creat new account</a>
            <a class="f_p" href="forget_password.html">forget password</a>
        </div>
    </body>
</html>
