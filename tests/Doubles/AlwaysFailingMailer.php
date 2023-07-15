<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Doubles;

use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Orisai\Exceptions\Logic\InvalidState;

final class AlwaysFailingMailer implements Mailer
{

	public function send(Message $mail): void
	{
		throw InvalidState::create()
			->withMessage('I am not sending that.');
	}

}
