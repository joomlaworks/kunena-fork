<?php

defined('_JEXEC') or die;
class PlgCaptchaTestcaptcha extends JPlugin
{
    public function onInit($id = 'test')
    {
        return true;
    }
    public function onDisplay($name = null, $id = 'test', $class = '')
    {
        return '<input type="text" class="testcaptcha" name="' . $name . '" id="' . $id . '">';
    }
    public function onCheckAnswer($code = null)
    {
        if ($code !== 'letmein') {
            throw new RuntimeException('TESTCAPTCHA wrong answer');
        }
        return true;
    }
}
