<?php

namespace B2Backblaze;

/**
 * B2Exception.
 *
 * @author Kamil Zabdyr <kamilzabdyr@gmail.com>
 */
class B2Exception extends \Exception
{
    public function __construct($message)
    {
        parent::__construct(sprintf('B2Client API error : %s', $message));
    }
}
