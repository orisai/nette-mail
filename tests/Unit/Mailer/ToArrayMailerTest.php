<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Mailer;

use Nette\Mail\Message;
use OriNette\Mail\Mailer\ToArrayMailer;
use PHPUnit\Framework\TestCase;

final class ToArrayMailerTest extends TestCase
{

	public function test(): void
	{
		$mailer = new ToArrayMailer();
		self::assertSame([], $mailer->getMessages());

		$message1 = new Message();
		$message1->addTo('foo@example.com');

		$mailer->send($message1);

		self::assertEquals(
			[
				$message1,
			],
			$mailer->getMessages(),
		);
		self::assertNotSame(
			[
				$message1,
			],
			$mailer->getMessages(),
		);

		$message2 = new Message();
		$message2->addTo('bar@example.com');

		$mailer->send($message2);

		self::assertEquals(
			[
				$message1,
				$message2,
			],
			$mailer->getMessages(),
		);

		$mailer->send($message1);

		self::assertEquals(
			[
				$message1,
				$message2,
				$message1,
			],
			$mailer->getMessages(),
		);
	}

}
