<?php

require_once __DIR__ . '/../vendor/autoload.php';

// начальные данные
// $key берем со страницы https://cptch.net/profile
$key = '64-символьный ключ здесь';

try {
    // создаем объект, который будет решать капчи
    $solver = new \Cptch\Cptch($key);

    // пробуем решить капчу, картинка с которой лежит по ссылке
    $captchaUrl = 'https://cptch.net/assets/images/sample.jpg';

    $captchaAnswer = $solver->solveImageByUrl($captchaUrl);

    // в переменной $captchaAnswer лежит ответ на капчу
    // если ответ на капчу не прошел, то можно отправить информацию о неправильном решении капчи
    // работника, который ошибся, забанят
    if ($captchaAnswer !== 'vnvk') {
        $solver->reportBadCaptchaSolution();
    }

    echo $captchaAnswer;
} catch (\Exception $ex) {
    // необходимо обрабатывать исключения
    // https://ru.wikipedia.org/wiki/Обработка_исключений
}
