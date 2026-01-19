<?php

@include 'config.php';

session_start();

if(isset($_POST['submit'])){

   $name = mysqli_real_escape_string($conn, $_POST['name']);
   $email = mysqli_real_escape_string($conn, $_POST['email']);
   $pass = md5($_POST['password']);
   $role_password = $_POST['role_password'];
   $user_type = $_POST['user_type'];

   $select = " SELECT * FROM user_form WHERE email = '$email' && password = '$pass' ";

   $result = mysqli_query($conn, $select);

   if(mysqli_num_rows($result) > 0){

      $row = mysqli_fetch_array($result);

      if($row['user_type'] == 'admin'){

         $_SESSION['user_name'] = $row['name'];
         header('location:user_page.php');

      }elseif($row['user_type'] == 'user'){

         $_SESSION['user_name'] = $row['name'];
         header('location:user_page.php');

      }
     
   }else{
      $error[] = 'incorrect email or password!';
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
      <h3>login now</h3>
      <?php
      if(isset($error)){
         foreach($error as $error){
            echo '<span class="error-msg">'.$error.'</span>';
         };
      };
      ?>
      <input type="email" name="email" required placeholder="enter your email">
      <input type="password" name="password" required placeholder="enter your password">
      <input type="submit" name="submit" value="login now" class="form-btn">
      <p>don't have an account? <a href="sign-up.php">register now</a></p>
      </form>

   </div>
  


</body>
</html>