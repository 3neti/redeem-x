<?php

use LBHurtado\OmniChannel\Contracts\AutoReplyInterface;
use LBHurtado\OmniChannel\Handlers\AutoReplies\HelpAutoReply;

it('implements the AutoReplyInterface', function () {
    $handler = new HelpAutoReply;
    expect($handler)->toBeInstanceOf(AutoReplyInterface::class);
});

it('returns the correct help message', function () {
    $handler = new HelpAutoReply;

    $from = '09171234567';
    $to = '22560537';
    $message = 'HELP please';

    $reply = $handler->reply($from, $to, $message);

    expect($reply)->toBe(
        "For support, reply with 'SUPPORT'. To contact an agent, reply 'AGENT'."
    );
});
