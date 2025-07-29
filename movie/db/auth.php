<?php

 require 'db.php';

 
 /** 
* Login de um utilizador
* @param string $username -> Nome de utilizador ou email
* @param string $password -> Password do utilizador
* @return bool -> true se o login foi bem sucedido, false caso contrário
*/
function login($identifier, $password) {
    global $con;

    $stmt = $con->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Incorrect password.'];
    }

    return ['success' => true, 'user' => $user];
}



function requireRole($roleId = 2, $redirect = '../views/login.php') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start(); // Start session only if not already started
    }

    if (!isset($_SESSION['user']) || $_SESSION['user']['role_id'] != $roleId) {
        header("Location: $redirect");
        exit(); // Ensure script stops after redirect
    }
}



 
/**
* Registo de um novo utilizador
* @param string $email     -> Email do utilizador
* @param string $username  -> Nome de utilizador
* @param string $password  -> Password do utilizador
* @param string $telemovel -> Número de telemóvel
* @param string $nif       -> Número de Identificação Fiscal
* @return bool -> true se o registo foi bem sucedido, false caso contrário
*/
/**function registo($email,$username,$password,$telemovel,$nif){
        global $con;
        //1º - Criar e preparar a query de insert
        $sql = $con->prepare('INSERT INTO Utilizador(email,username,password,telemovel,nif,token) VALUES (?,?,?,?,?,?)');
        //2º - Gerar o token aletório
        $token = bin2hex(random_bytes(16));
        //3º - Encriptar a password
        $password = password_hash($password, PASSWORD_DEFAULT);
        //4º - Colocar os dados na query e executar a mesma e ver se deu certo
        $sql->bind_param('ssssss',$email,$username,$password,$telemovel,$nif,$token);
        $sql->execute();
        if($sql->affected_rows > 0){
            //5º - Enviar o email com o token para ativar a conta
            send_email($email,'Ativar a conta',$token);
            return true;
        }else{
            //O registo falhou
            return false;
        }
}
        */

/**
 * Ativa a conta de um utilizador com base no email e token fornecidos.
 *
 * @param string $email Email do utilizador a ativar
 * @param string $token Token de ativação enviado por email
 * @return bool true se a conta foi ativada com sucesso, false caso contrário

function ativarConta($email, $token){
    global $con;

    // 1º - Ver se existe um registo com o email e o token
    $sql = $con->prepare("SELECT * FROM Utilizador WHERE email = ? AND token = ?");
    $sql->bind_param('ss', $email, $token);
    $sql->execute();
    $result = $sql->get_result();

    if($result->num_rows > 0){
        // 2º - Se existir, fazer update ao campo 'active' e apagar o token
        $sqlUpdate = $con->prepare("UPDATE Utilizador SET active = 1, token = NULL WHERE email = ?");
        $sqlUpdate->bind_param('s', $email);
        $sqlUpdate->execute();
        return true;
    } else {
        // 3º - Se não existir, retornar false
        return false;
    }
}
    */





function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = array();
    session_destroy();

    //Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Redirect to index page
    header("Location:index.php");
    exit;
}
 

function isAdmin(){
  return isset($_SESSION["user"]["role_id"]) && $_SESSION["user"]["role_id"] == 2;
}