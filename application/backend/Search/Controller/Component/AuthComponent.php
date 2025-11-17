<?php

declare(strict_types=1);

namespace Search\Controller\Component;

use Search\Authentication\Auth;
use Search\Controller\BaseController;
use Search\Controller\Component;
use Search\Error\Exceptions;
use Search\Security\BaseCriptography;

/**
 *
 */
class AuthComponent extends Component
{
    /**
     * @var Auth
     */
    private Auth $auth;

    /**
     * @var BaseCriptography
     */
    private BaseCriptography $cryptography;

    /**
     * @var array
     */
    private array $pages = [];

    /**
     * @var array
     */
    private array $config;

    /**
     * @param BaseController $controller
     * @throws Exceptions
     */
    public function __construct(BaseController $controller)
    {
        parent::__construct($controller);
        $this->auth = $this->request->auth;
        $this->cryptography = $this->request->bootstrap('security')->criptography();
    }

    /**
     * @param array $config
     * @return $this
     * @throws Exceptions
     */
    public function configDefinitionsOfThisClass(array $config, array $redirects = []): AuthComponent
    {
        if (count($redirects) !== 6) {
            if (count($redirects) > 6) {
                throw new Exceptions(
                    'The variable redirects can only contain 6 keys and their respective values.',
                    404
                );
            }
            $redirects = [
                'unlogged' => 'main+logout',
                'logging' => 'main+logging',
                'logged' => 'main+dashboard',
                'login' => 'main+login',
                'twoSteps' => 'main+secondfactor',
                'home' => 'main+index'
            ];
        } else {
            foreach (['unlogged', 'logging', 'logged', 'login', 'home', 'twoSteps'] as $key) {
                if (!isset($redirects[$key])) {
                    throw new Exceptions(
                        'One of these keys was not found: unlogged, logged, logging, login, home or twoSteps.',
                        404
                    );
                }
            }
        }
        $this->redirectsPath($redirects);
        $twoFactorAuth = false;
        if (isset($config['twoFactorAuth'])) {
            $twoFactorAuth = $config['twoFactorAuth'];
            unset($config['twoFactorAuth']);
        }
        if (!$twoFactorAuth) {
            unset($this->config['redirect']['twoSteps']);
        }
        foreach (['authenticate'] as $key) {
            if (in_array($key, array_keys($config)) === false) {
                throw new Exceptions(
                    "The auth component\'s authentication must contain the following keys: authenticate.",
                    404,
                );
            }
        }
        if (isset($config['pages']) && count($config['pages']) > 0) {
            if (!isset($config['pages']['main']) || in_array('index', $config['pages']['main']) === false) {
                $config['pages'] = array_merge($config['pages'], ['main' => ['index']]);
            }
            $this->pages($config['pages']);
            unset($config['pages']);
        }
        if ($this->auth->checkingTypeAndKeyExists('', 'cookie')) {
            foreach ($this->auth->datas('', 'cookie') as $key => $value) {
                if (in_array($key, $config['cookie']) && $config['cookie'][$key] !== $value) {
                    $config['cookie'][$key] = $value;
                } elseif (in_array($key, $config['cookie']) === false) {
                    $config['cookie'][$key] = $value;
                }
            }
        }
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * @param array|string $redirects
     * @return mixed
     * @throws Exceptions
     */
    public function redirectsPath(array|string $redirects): mixed
    {
        if (is_array($redirects)) {
            foreach ($redirects as $name => $redirect) {
                $this->config['redirect'][$name] = $this->controller->linkEncrypt(['link' => $redirect, 'prefix' => 'app']);
            }
            return $this;
        }
        return $this->config['redirect'][$redirects];
    }

    /**
     * @param array $pages
     * @return $this
     */
    public function pages(array $pages): AuthComponent
    {
        $newPages = [];
        foreach ($pages as $key => $values) {
            foreach ($values as $value) {
                $newPages[$key] = count($newPages) > 0 && in_array(
                    $value,
                    $newPages[$key]
                ) === false ? (array_key_exists($key, $newPages) !== false ? array_merge(
                    $newPages[$key],
                    [$value]
                ) : [$value]) : [$value];
            }
        }
        $this->pages = $newPages;
        return $this;
    }

    /**
     * @return $this
     * @throws Exceptions
     */
    public function identifyEncryptActive(): AuthComponent
    {
        if ($this->validEncrypt()) {
            $this->cryptography->changeConfig('3');
            if ($this->controller->action !== 'secondFactor') {
                $this->controller->encrypted = !$this->controller->encrypted;
            }
        }
        return $this;
    }

    /**
     * @return bool
     * @throws Exceptions
     */
    private function validEncrypt(): bool
    {
        if ($this->checkingAuthenticated()) {
            return $this->request->encryptionKeys['internal'];
        }
        if ($this->request->encryptionKeys['linkInternalWithExternalAccess']) {
            return true;
        }
        return $this->request->encryptionKeys['general'];
    }

    /**
     * @param bool $notUseKeyEncryptionAuth
     * @return bool
     * @throws Exceptions
     */
    public function checkingAuthenticated(bool $notUseKeyEncryptionAuth = false): bool
    {
        $check = $this->auth->checkingTypeAndKeyExists('user');
        if (!$notUseKeyEncryptionAuth) {
            return $check;
        }
        if ($check) {
            if ($this->controller->name !== 'Main' || ($this->controller->name === 'Main' && $this->controller->action === 'secondFactor')) {
                $idUser = $this->authenticated()['id'];
                $keysEncryptionAuth = $this->auth->datas('keysEncryption');
                if (isset($keysEncryptionAuth[$idUser])) {
                    return $keysEncryptionAuth[$idUser]['status'] === 'connect';
                }
                $checked = false;
                if ($keysEncryptionAuth[0]['status'] === 'desconnect') {
                    $keysEncryptionAuth[$idUser] = $keysEncryptionAuth[0];
                    $keysEncryptionAuth[$idUser]['status'] = 'connect';
                    $this->controller->keysEncryptionAuthIdentify($keysEncryptionAuth);
                    $checked = !$checked;
                }
                return $checked;
            }
            return false;
        }
        return false;
    }

    /**
     * @return array
     * @throws Exceptions
     */
    public function authenticated(): array
    {
        if ($this->auth->checkingTypeAndKeyExists('user')) {
            return $this->auth->authenticated();
        }
        return [];
    }

    /**
     * @param bool $ceckedLogged
     * @return array
     * @throws Exceptions
     */
    public function checkingIfItIsAuthenticated(bool $ceckedLogged): array
    {
        $resultValidated = false;
        if ($ceckedLogged) {
            foreach (explode(DS, $this->request->route) as $count => $data) {
                if ($count < 2) {
                    $dataRoute[] = $data;
                }
            }
            $route = implode(DS, $dataRoute);
            unset($dataRoute);
            foreach (explode(DS, $this->config['redirect']['login']) as $count => $data) {
                if ($count < 2) {
                    $dataRedirect[] = $data;
                }
            }
            $redirect = implode(DS, $dataRedirect);
            unset($dataRedirect);
            if ($route === $redirect) {
                $resultValidated = !$resultValidated;
            }
        }
        return $resultValidated ? [
            'validated' => $resultValidated,
            'redirect' => isset($this->config['redirect']['twoSteps']) && !$this->auth->checkingTypeAndKeyExists(
                '2fa',
                'cookie'
            ) ? 'twoSteps' : 'logged'
        ] : ['validated' => $resultValidated];
    }

    /**
     * @param bool $ceckedLogged
     * @param string $route
     * @return bool
     */
    public function checkingIfTheIndicatedRouteIsDifferentFromTheRoutes(bool $ceckedLogged, string $route): bool
    {
        $resultValidated = false;
        $route = explode(DS, $route);
        if ($this->request->prefix != 'app') {
            array_splice($route, 0, 1);
        }
        if (count($route) > 2) {
            foreach ($route as $number => $data) {
                if (in_array($number, [0, 1]) !== false) {
                    $newRoute[] = $data;
                }
            }
            $route = $newRoute;
            unset($newRoute);
        }
        $route = implode(DS, $route);
        if (isset($this->config['redirect']['twoSteps'])) {
            $configRedirect = explode(DS, $this->config['redirect']['twoSteps']);
            if ($this->request->prefix != 'app') {
                array_splice($configRedirect, 0, 1);
            }
            if (count($configRedirect) > 2) {
                foreach ($configRedirect as $number => $data) {
                    if (in_array($number, [0, 1]) !== false) {
                        $newConfigRedirect[] = $data;
                    }
                }
                $configRedirect = $newConfigRedirect;
                unset($newConfigRedirect);
            }
            $routes[] = substr(implode(DS, $configRedirect), 1);
        }
        if (isset($this->config['redirect']['logged'])) {
            $routes[] = substr($this->config['redirect']['logged'], 1);
        }
        foreach (array_keys($this->pages) as $key) {
            $routes[] = array_merge($routes, $this->pages[$key]);
        }
        if (in_array($route, $routes) && $ceckedLogged === false) {
            $resultValidated = !$resultValidated;
        }
        return $resultValidated;
    }

    /**
     * @param string $key
     * @param bool $delete
     * @return array
     * @throws Exceptions
     */
    public function returnTheOtherData(string $key, bool $delete = false): array
    {
        $datas = $this->auth->datas($key);
        if ($delete) {
            $this->auth->destroy(['key' => $key]);
        }
        return $datas;
    }

    /**
     * @param bool $existsCookies
     * @return void
     * @throws Exceptions
     */
    public function destroyAuthenticated(bool $existsCookies = false): AuthComponent
    {
        $user = $this->authenticated();
        if (count($user) > 0) {
            $this->cryptography->oldIdUser($user['id'])->keysEncryptionForThisUser(
                '0',
            )->deleteByTimeOrIdentifier(
                [],
                false,
                $user['id']
            );
        }
        $this->auth->destroy(['key' => 'user']);
        if ($existsCookies) {
            if (
                isset($this->config['redirect']['twoSteps']) && !$this->auth->checkingTypeAndKeyExists(
                    '2fa',
                    'cookie'
                )
            ) {
                $this->auth->destroy(['key' => '2fa'], 'cookies');
            }
            if ($this->auth->checkingTypeAndKeyExists('user', 'cookie')) {
                $this->auth->destroy(['key' => 'user'], 'cookies');
            }
        }
        $this->controller->linkEncrypt(['link' => $this->config['redirect']['home'], 'prefix' => 'app']);
        return $this;
    }

    /**
     * @param string $key
     * @param array $data
     * @param bool $force
     * @return $this
     * @throws Exceptions
     */
    public function writingTheOtherData(string $key, array $data, bool $force = false): AuthComponent
    {
        if (!$force) {
            $counts = 0;
            foreach (array_keys($data) as $subkey) {
                if (is_numeric($subkey)) {
                    continue;
                }
                if ($this->checkingTheOtherData($key, $subkey)) {
                    continue;
                }
                $counts++;
            }
            if ($counts === 0) {
                $this->auth->write(['key' => $key, 'values' => $data]);
            }
            return $this;
        }
        $this->auth->write(['key' => $key, 'values' => $data]);
        return $this;
    }

    /**
     * @param string $key
     * @param string|null $subkey
     * @return bool
     * @throws Exceptions
     */
    public function checkingTheOtherData(string $key, ?string $subkey = null): bool
    {
        if (!isset($subkey)) {
            return $this->auth->checkingTypeAndKeyExists($key);
        }
        $values = $this->auth->datas($key);
        return !isset($values[$subkey]) && $values[$subkey] === '';
    }

    /**
     * @param array $values
     * @param string $status
     * @param array $cookies
     * @return $this
     * @throws Exceptions
     */
    public function authenticating(array $data, string $status, array $cookies = ['valid' => false]): AuthComponent
    {
        foreach ($data as $Key => $value) {
            if (!is_string($value) && is_int($value)) {
                $data[$Key] = (string) $value;
            }
        }
        $this->auth->write(['key' => 'user', 'values' => $data]);
        if ($cookies['valid']) {
            unset($cookies['valid']);
            $this->auth->write(array_merge(['key' => 'user'], $cookies), 'cookie');
        }
        $redirect = $this->config['redirect']['logged'];
        $urlParams = '';
        if (isset($this->config['redirect']['twoSteps']) && !$this->auth->checkingTypeAndKeyExists('2fa', 'cookie')) {
            $redirect = $this->config['redirect']['twoSteps'];
            $urlParams = $status === 'ativo' ? 'validate' : 'active';
        }
        $this->controller->linkEncrypt(['link' => $redirect, 'prefix' => 'app', 'urlParams' => $urlParams]);
        $this->cryptography->keysEncryptionForThisUser($data['id']);
        return $this;
    }

    /**
     * @param $isTheNecessaryRoute
     * @return string
     * @throws Exceptions
     */
    public function sendThisDataOfJavascript(): string
    {
        $validated = ['restrictedArea' => 'arrested', '2fa' => 'arrested'];
        if ($this->checkingAuthenticated()) {
            $validated['restrictedArea'] = 'released';
            if ($this->auth->checkingTypeAndKeyExists('user', 'cookie')) {
                $validated['restrictedArea'] = 'arrested';
            }
        }
        if (isset($this->config['redirect']['twoSteps'])) {
            foreach (explode(DS, $this->request->route) as $count => $data) {
                if ($count < 2) {
                    $dataRoute[] = $data;
                }
            }
            $route = implode(DS, $dataRoute);
            unset($dataRoute);
            foreach (explode(DS, $this->config['redirect']['twoSteps']) as $count => $data) {
                if ($count < 2) {
                    $dataRedirect[] = $data;
                }
            }
            $redirect = implode(DS, $dataRedirect);
            unset($dataRedirect);
            if ($route === $redirect) {
                $validated['2fa'] = 'released';
                if ($this->auth->checkingTypeAndKeyExists('2fa', 'cookie')) {
                    $validated['2fa'] = 'arrested';
                }
            } else {
                if (!$this->auth->checkingTypeAndKeyExists('2fa', 'cookie')) {
                    $validated['2fa'] = 'released';
                }
            }
        }
        return 'window.stopwatchMode = ' . json_encode($validated, JSON_FORCE_OBJECT) . ';' . PHP_EOL;
    }

    /**
     * @param array $conditions
     * @return bool
     */
    public function validationConditionsToFetchAuthenticationData(array $conditions): bool
    {
        $access = 0;
        foreach ($this->config['authenticate'] as $condition) {
            if (in_array($condition, array_keys($conditions)) === false) {
                $access++;
            }
        }
        return $access !== 0;
    }

    /**
     * @param string $key
     * @return $this
     * @throws Exceptions
     */
    public function destroyTheOtherData(string $key, string $type = 'session'): AuthComponent
    {
        $this->auth->destroy(['key' => $key], $type);
        return $this;
    }

    /**
     * @param string $type
     * @param array $options
     * @return string|array
     * @throws Exceptions
     */
    public function twoFactorAuth(string $type, array $options = []): string|array
    {
        if ($type === 'enviarCodeForEmail') {
            return $this->auth->codeEmailTwoSteps($options['secret'] ?? '');
        }
        if ($type === 'active' || $type === 'qrcode') {
            if (!isset($options['length'])) {
                $options['length'] = 16;
            }
            $this->auth->activeTwoSteps(
                $options['name'],
                $options['email'],
                $options['length'],
                $options['prefix'] ?? '',
                $type === 'qrcode'
            );
            return $this->auth->datas($type === 'active' ? '' : 'qrCode', 'twoFactorAuth');
        }
        $validated = $this->auth->validCodeTwoSteps(
            $options['secret'],
            $options['code'],
            $options['recoveries'],
            $options['name'],
            $options['email']
        );
        if (strlen($options['code']) === 6) {
            if ($validated) {
                if (isset($this->config['redirect']['twoSteps']) && $options['createCookie']) {
                    $this->auth->write(['key' => '2fa', 'values' => ''], 'cookie');
                }
                $this->controller->linkEncrypt(['link' => $this->config['redirect']['logged'], 'prefix' => 'app']);
                return ['valid' => true];
            }
            return ['valid' => false];
        }
        if ($validated) {
            if (isset($this->config['redirect']['twoSteps']) && $options['createCookie']) {
                $this->auth->write(['key' => '2fa', 'values' => ''], 'cookie');
            }
            $this->controller->linkEncrypt(['link' => $this->config['redirect']['twoSteps'], 'prefix' => 'app', 'urlParams' => 'validatedRecovery']);
            return ['valid' => true];
        }
        return ['valid' => false, 'twoSteps' => $this->auth->datas('', 'twoFactorAuth')];
    }
}