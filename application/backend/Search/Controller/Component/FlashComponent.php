<?php

declare(strict_types=1);

namespace Search\Controller\Component;

use DataTime;
use Search\Authentication\Auth;
use Search\Controller\BaseController;
use Search\Controller\Component;
use Search\Error\Exceptions;

/**
 *
 */
class FlashComponent extends Component
{
    /**
     * @var array
     */
    private array $messageTypes = ['e' => 'error', 'a' => 'warning', 's' => 'success', 'i' => 'info'];

    /**
     * @var Auth
     */
    private Auth $auth;

    /**
     * @param BaseController $controller
     * @throws Exceptions
     */
    public function __construct(BaseController $controller)
    {
        parent::__construct($controller);
        $this->auth = $this->request->auth;
        $key = 'flash_messages';
        if (!$this->auth->checkingTypeAndKeyExists($key)) {
            $this->auth->write(['key' => 'flash_messages', 'values' => []]);
        } else {
            $flash = $this->auth->datas($key);
            if (count($flash) > 0) {
                $this->auth->write(['key' => $key, 'values' => $flash]);
            }
            unset($flash);
        }
    }

    /**
     * @param string $message
     * @return $this
     * @throws Exceptions
     */
    public function error(string $message): FlashComponent
    {
        $this->add($message, 'e');
        return $this;
    }

    /**
     * @param string $message
     * @param string $type
     * @return $this
     * @throws Exceptions
     */
    private function add(string $message, string $type): FlashComponent
    {
        if (strlen(trim($type)) > 1) {
            $type = strtolower($type[0]);
        }
        if (!$this->auth->checkingTypeAndKeyExists('flash_messages')) {
            $flash = $this->auth->datas('flash_messages');
            if (isset($flash[$type])) {
                if (!$this->sweep($flash[$type], substr($message, 19))) {
                    $flash[$type][] = $message;
                }
            } else {
                $flash[$type][] = $message;
            }
            $this->auth->write(['key' => 'flash_messages', 'values' => $flash]);
        }
        return $this;
    }

    /**
     * @param array $flash
     * @param string $message
     * @return bool
     */
    private function sweep(array $flash, string $message): bool
    {
        $validated = false;
        foreach ($flash as $msg) {
            if (substr($msg, 19) === $message) {
                $validated = !$validated;
                break;
            }
        }
        return $validated;
    }

    /**
     * @param array $messages
     * @return $this
     * @throws Exceptions
     */
    public function errors(array $messages): FlashComponent
    {
        $this->adds($messages, 'e');
        return $this;
    }

    /**
     * @param array $messages
     * @param string $type
     * @return $this
     * @throws Exceptions
     */
    private function adds(array $messages, string $type): FlashComponent
    {
        if (strlen(trim($type)) > 1) {
            $type = strtolower($type[0]);
        }
        if (!$this->auth->checkingTypeAndKeyExists('flash_messages')) {
            $flash = $this->auth->datas('flash_messages');
            $flash[$type] = isset($flash[$type]) ? array_merge($flash[$type], $messages) : $messages;
            $this->auth->write(['key' => 'flash_messages', 'values' => $flash]);
        }
        return $this;
    }

    /**
     * @param array $messages
     * @return $this
     * @throws Exceptions
     */
    public function infos(array $messages): FlashComponent
    {
        $this->adds($messages, 'i');
        return $this;
    }

    /**
     * @param array $messages
     * @return $this
     * @throws Exceptions
     */
    public function warnings(array $messages): FlashComponent
    {
        $this->adds($messages, 'w');
        return $this;
    }

    /**
     * @param array $messages
     * @return $this
     * @throws Exceptions
     */
    public function successes(array $messages): FlashComponent
    {
        $this->adds($messages, 's');
        return $this;
    }

    /**
     * @param string $message
     * @return $this
     * @throws Exceptions
     */
    public function info(string $message): FlashComponent
    {
        $this->add($message, 'i');
        return $this;
    }

    /**
     * @param string $message
     * @return $this
     * @throws Exceptions
     */
    public function warning(string $message): FlashComponent
    {
        $this->add($message, 'w');
        return $this;
    }

    /**
     * @param string $message
     * @param string|null $url
     * @return $this
     * @throws Exceptions
     */
    public function success(string $message): FlashComponent
    {
        $this->add($message, 's');
        return $this;
    }

    /**
     * @param bool $render
     * @return mixed
     * @throws Exceptions
     */
    public function handler(bool $render = false): mixed
    {
        $flash = $this->auth->datas('flash_messages');
        if ($render) {
            $content = ['error' => '', 'warning' => '', 'success' => '', 'info' => ''];
            if (count($flash) > 0) {
                foreach ($this->messageTypes as $key => $type) {
                    if (in_array($key, array_keys($flash)) !== false) {
                        $content[$type] = is_array($flash[$key]) ? implode('<br>', $flash[$key]) : $flash[$key];
                    }
                }
            }
            return $content;
        }
        if (count($flash) > 0) {
            foreach ($this->messageTypes as $keyType => $type) {
                if (stripos($this->request->route, $type) !== false) {
                    $this->{$type}(is_array($flash[$keyType]) ? implode('<br>', $flash[$keyType]) : $flash[$keyType]);
                }
            }
        }
        return $this;
    }
}
