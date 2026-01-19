<?php

@include 'config.php';

if(isset($_POST['submit'])){

   $name = mysqli_real_escape_string($conn, $_POST['name']);
   $email = mysqli_real_escape_string($conn, $_POST['email']);
   $pass = md5($_POST['password']);
    $role_password = $_POST['role_password'];
   $user_type = $_POST['user_type'];

   $role_passwords = [
    'admin' => 'PROF2025',
    'user' => 'STUD2025'  
];

    $role_verified = false;
    
    if ($user_type === 'admin' && $role_password === $role_passwords['admin']) {
        $role_verified = true;
    } elseif ($user_type === 'user' && $role_password === $role_passwords['user']) {
        $role_verified = true;
    }
    
    if (!$role_verified) {
        die("Error: Incorrect role password. Please enter the correct password for your selected role.");
    }

   $select = " SELECT * FROM user_form WHERE email = '$email' && password = '$pass' ";

   $result = mysqli_query($conn, $select);

   if(mysqli_num_rows($result) > 0){

      $error[] = 'user already exist!';
   }else{      
         $insert = "INSERT INTO user_form(name, email, password,user_type,role_password) VALUES('$name','$email','$pass','$user_type','$role_password')";
         mysqli_query($conn, $insert);
         header('location:sign-in.php');
   }

};


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project</title>
    <link rel="stylesheet" href="css/Style1.css">
    <link rel="stylesheet" href="css/log2.css">    

</head>
<body>
  <nav>
    <ul class="sidebar">
        <li onclick=hideSidebar()><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000ff"><path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg></a></li>
        <li><a href="inedx.htm">Αρχική</a></li>
        <li><a href="cms.htm">Σπουδές</a></li>
        <li><a href="cms.htm">Φοιτητές</a></li>
        <li><a href="sign-up.php">Εγγραφή</a></li>
        <li><a href="sign-in.php">Σύνδεση</a></li>
    </ul>
    <ul>
        <li class="hideOnMobile"><a href="inedx.htm">Αρχική</a></li>
        <li class="hideOnMobile"><a href="cms.htm">Σπουδές</a></li>
        <li class="hideOnMobile"><a href="cms.htm">Φοιτητές</a></li>
        <li class="hideOnMobile"><a href="sign-up.php">Εγγραφή</a></li>
        <li class="hideOnMobile"><a href="sign-in.php">Σύνδεση</a></li>
        <li class="menu-button" onclick=showSidebar() ><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000ff"><path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/></svg></a></li>
    </ul>
   </nav> 
   
   <script>
    function showSidebar(){
        const sidebar = document.querySelector('.sidebar')
        sidebar.style.display = 'flex'
    }
    function hideSidebar(){
        const sidebar = document.querySelector('.sidebar')
        sidebar.style.display = 'none'
    }
   </script>

    
               
<div class="form-container">

   <form action="" method="post">
      <h3>Register Now</h3>
      <?php
      if(isset($error)){
         foreach($error as $error){
            echo '<span class="error-msg">'.$error.'</span>';
         };
      };
      ?>
      <input type="text" name="name" required placeholder="enter your name">
      <input type="email" name="email" required placeholder="enter your email">
      <input type="password" name="password" required placeholder="enter your password">
      <input type="password" name="role_password" required placeholder="enter your role password">
      <select name="user_type">
         <option value="user">student</option>
         <option value="admin">teacher</option>
      </select>
      <input type="submit" name="submit" value="register now" class="form-btn">
      <p>already have an account? <a href="sign-in.php">login now</a></p>
   </form>


</div>

  <footer>


   <script>
        ent.addEventListener('click', (event) => {
            const sidebar = document.querySelector('.sidebar');
            const menuButton = document.querySelector('.menu-button');
            
            if (sidebar.style.display === 'flex' && 
                !sidebar.contains(event.target) && 
                !menuButton.contains(event.target)) {
                sidebar.style.display = 'none';
            }
        });
    </script>

</body>
</html>




