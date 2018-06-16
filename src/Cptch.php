<?php

namespace Cptch;

use Cptch\Exception\EmptyKeyException;
use Cptch\Exception\InvalidImagePathException;
use Cptch\Exception\InvalidImageUrlException;
use Cptch\Exception\InvalidParametersException;
use Cptch\Exception\TransportException;

class Cptch
{

    /**
     * Параметр key
     * @var string $accessToken
     */
    protected $accessToken;

    /**
     * Последний ID капчи cptch.net для указания ошибочного решения капч
     * @var integer $lastCaptchaId
     */
    protected $lastCaptchaId;

    /**
     * Массив параметров, которые передаются во всех запросах к API Cptch.net
     * @var array
     */
    protected $defaultPostFields;

    /**
     * Cptch constructor.
     * @param string $key
     * @throws EmptyKeyException
     */
    public function __construct($key)
    {
        if (empty($key) || !is_string($key)) {
            throw new EmptyKeyException();
        }

        $this->accessToken = $key;
        $this->defaultPostFields = array(
            'key' => $this->accessToken,
            'soft_id' => '38',
        );
    }

    /**
     * Возвращает установленный параметр key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->accessToken;
    }

    /**
     * Устанавливает новый параметр key
     *
     * @param $newKey
     * @return Cptch
     */
    public function setKey($newKey)
    {
        $this->accessToken = $newKey;

        return $this;
    }

    /**
     * Возвращает баланс пользователя по его key
     *
     * @throws TransportException
     * @return float
     */
    public function getBalance()
    {
        $requestParams = array(
            'action' => 'getbalance',
            'key' => $this->accessToken,
        );

        $response = $this->cptchPostRequest('res.php', $requestParams);

        return (float)$response;
    }

    /**
     * Возвращает текст, написанный на капче, по его ссылке в Интернете
     *
     * @param string|null $imageUrl
     * @throws InvalidImageUrlException
     * @return string
     * @throws TransportException
     * @throws InvalidParametersException
     */
    public function solveImageByUrl($imageUrl = null)
    {
        if (!is_string($imageUrl)) {
            throw new InvalidImageUrlException();
        }

        $image = $this->webGetRequest($imageUrl);

        $postFields = array_merge($this->defaultPostFields, array(
            'method' => 'base64',
            'body' => base64_encode($image)
        ));

        return $this->solveTask($postFields);
    }

    /**
     * Возвращает текст, написанный на капче, по его расположению на локальном жестком диске
     *
     * @param string $localPath
     * @return string
     * @throws InvalidImagePathException
     * @throws InvalidParametersException
     * @throws TransportException
     */
    public function solveImageByLocalPath($localPath)
    {
        if (!is_string($localPath) || !file_exists($localPath)) {
            throw new InvalidImagePathException();
        }

        $image = file_get_contents($localPath);

        $postFields = array_merge($this->defaultPostFields, array(
            'method' => 'base64',
            'body' => base64_encode($image)
        ));

        return $this->solveTask($postFields);
    }

    /**
     * @param string $googleKey
     * @param $pageUrl
     * @return string
     * @throws InvalidParametersException
     * @throws TransportException
     */
    public function solveRecaptcha($googleKey, $pageUrl)
    {
        if (empty($googleKey) || empty($pageUrl) || !is_string($googleKey) || !is_string($pageUrl)) {
            throw new InvalidParametersException();
        }

        $postFields = array_merge($this->defaultPostFields, array(
            'method' => 'userrecaptcha',
            'googlekey' => $googleKey,
            'pageurl' => $pageUrl
        ));

        return $this->solveTask($postFields);
    }

    /**
     * Посылает информацию о неправильном решении капчи
     * @return void
     * @throws TransportException
     */
    public function reportBadCaptchaSolution()
    {
        $postFields = array(
            'key' => $this->accessToken,
            'action' => 'reportbad',
            'id' => $this->lastCaptchaId,
        );

        $this->cptchPostRequest('res.php', $postFields);
    }

    /**
     * Процесс решения капчи
     *
     * @param array $postFields
     * @return string
     * @throws InvalidParametersException
     * @throws TransportException
     */
    protected function solveTask($postFields)
    {
        $cptchResponse = $this->cptchPostRequest('in.php', $postFields);

        if (strpos($cptchResponse, 'ERROR') !== false) {
            throw new InvalidParametersException($cptchResponse);
        }

        $exploded = explode('|', $cptchResponse);
        if ($exploded[0] !== 'OK') {
            throw new InvalidParametersException($cptchResponse);
        }

        $this->lastCaptchaId = $exploded[1];
        $captchaAnswer = null;

        $postFields = array(
            'key' => $this->accessToken,
            'action' => 'get',
            'id' => $this->lastCaptchaId
        );

        while ($captchaAnswer === null) {
            sleep(1);

            $cptchGetAnswerResponse = $this->cptchPostRequest('res.php', $postFields);

            if (strpos($cptchGetAnswerResponse, 'ERROR')) {
                throw new InvalidParametersException($cptchGetAnswerResponse);
            }

            if ($cptchGetAnswerResponse == 'CAPCHA_NOT_READY') {
                continue;
            }

            $exploded = explode('|', $cptchGetAnswerResponse);
            $captchaAnswer = (trim($exploded[0]) == 'OK')
                ? $exploded[1]
                : null;
        }

        return $captchaAnswer;
    }

    /**
     * @param string $endpoint
     * @param array $postFields
     * @return string
     * @throws TransportException
     */
    protected function cptchPostRequest($endpoint, $postFields)
    {
        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://cptch.net/' . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $postFields,
        ));

        $response = curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new TransportException($error);
        }

        curl_close($ch);

        return $response;
    }

    /**
     * Возвращает содержимое по его ссылке в Интернете
     *
     * @param string $url
     * @return string
     * @throws TransportException
     */
    protected function webGetRequest($url)
    {
        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ));

        $response = curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new TransportException($error);
        }

        curl_close($ch);

        return $response;
    }
}
