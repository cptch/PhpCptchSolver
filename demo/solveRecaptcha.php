<?php

require_once __DIR__ . '/../vendor/autoload.php';

// начальные данные
// $key берем со страницы https://cptch.net/profile
$key = '64-символьный ключ здесь';

try {
    // создаем объект, который будет решать капчи
    $solver = new \Cptch\Cptch($key);

    // пробуем решить рекапчу, которая стоит на авторизации сайта cptch.net :)
    $googleKey = '6Le1j1oUAAAAABSlLbyDqrykXkNs8C87sk6eqGSF';
    $pageUrl = 'https://cptch.net/auth/login';

    $captchaAnswer = $solver->solveRecaptcha($googleKey, $pageUrl);

    // в переменной $captchaAnswer лежит ответ на капчу
    // если ответ на капчу не прошел, то можно отправить информацию о неправильном решении капчи
    // работника, который ошибся, забанят
    if (false) {
        $solver->reportBadCaptchaSolution();
    }

    echo $captchaAnswer;
} catch (\Exception $ex) {
    // необходимо обрабатывать исключения
    // https://ru.wikipedia.org/wiki/Обработка_исключений
}
