
全民淘金

===============

密码生成规则
password_hash($password1,PASSWORD_DEFAULT);

验证密码
$inputValue = '123456'; //用户输入的密码
password_verify( $inputValue, '存储的加密字符串');
