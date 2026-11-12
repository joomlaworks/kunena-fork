<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Baldrick
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

/**
 * Error from an AI provider or from Baldrick's processing.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaBaldrickException extends RuntimeException
{
    /**
     * @var boolean  Whether trying again later may succeed (rate limits, overloaded or unreachable provider)
     * @since 5.3.0
     */
    public $retryable = false;

    /**
     * @var boolean  The model declined the request (Anthropic stop_reason "refusal")
     * @since 5.3.0
     */
    public $refusal = false;

    /**
     * @var boolean  A problem of the account or the configuration (invalid or expired key, no credits left, quota
     *               exhausted, unknown model or endpoint): every request will fail until an administrator fixes it
     * @since 5.3.0
     */
    public $account = false;

    /**
     * @var integer  Seconds the provider asked to wait before trying again (0: not given)
     * @since 5.3.0
     */
    public $retryAfter = 0;

    /**
     * @var array  Token usage of a request which got an answer that could not be used
     * @since 5.3.0
     */
    public $usage = array();

    /**
     * @param   string   $message    Message
     * @param   integer  $code       HTTP status (0 for none)
     * @param   boolean  $retryable  Trying again later may succeed
     *
     * @since 5.3.0
     */
    public function __construct($message, $code = 0, $retryable = false)
    {
        parent::__construct($message, (int) $code);

        $this->retryable = (bool) $retryable;
    }
}
