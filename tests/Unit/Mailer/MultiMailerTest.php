<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Mailer;

use Nette\Mail\Message;
use OriNette\Mail\Mailer\MultiMailer;
use OriNette\Mail\Mailer\ToArrayMailer;
use Orisai\Exceptions\Logic\InvalidState;
use PHPUnit\Framework\TestCase;
use Tests\OriNette\Mail\Doubles\AlwaysFailingMailer;

final class MultiMailerTest extends TestCase
{

	public function testSuccess(): void
	{
		$inner1 = new ToArrayMailer();
		$inner2 = new ToArrayMailer();
		$mailer = new MultiMailer([$inner1, $inner2]);

		$message = new Message();
		$message->setHeader('X-Unique', 'Unicorn');
		$mailer->send($message);

		self::assertEquals([$message], $inner1->getMessages());
		self::assertEquals([$message], $inner2->getMessages());
	}

	public function testSingleException(): void
	{
		$inner1 = new ToArrayMailer();
		$inner2 = new AlwaysFailingMailer();
		$mailer = new MultiMailer([$inner1, $inner2]);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage('I am not sending that.');

		$mailer->send(new Message());
	}

	public function testMultipleExceptions(): void
	{
		$inner1 = new AlwaysFailingMailer();
		$inner2 = new AlwaysFailingMailer();
		$mailer = new MultiMailer([$inner1, $inner2]);

		$exception = null;
		try {
			$mailer->send(new Message());
		} catch (InvalidState $exception) {
			// Handled bellow
		}

		self::assertNotNull($exception);
		self::assertCount(2, $exception->getSuppressed());
	}

}
