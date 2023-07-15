<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Command;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use OriNette\Mail\Command\MailerTestCommand;
use OriNette\Mail\Mailer\ToArrayMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use function array_shift;

final class MailerTestCommandTest extends TestCase
{

	public function testMailSent(): void
	{
		$mailer = new ToArrayMailer();

		$command = new MailerTestCommand($mailer);
		$tester = new CommandTester($command);

		self::assertSame(
			<<<'MSG'
Test mailer by sending an email:
<info>php %command.full_name% to@example.com</info>
MSG,
			$command->getHelp(),
		);

		$code = $tester->execute(
			[
				'to' => 'ray.tomlinson@arpa.net',
			],
		);

		self::assertSame(
			<<<'MSG'
Mail sent.

MSG,
			$tester->getDisplay(),
		);
		self::assertSame($command::SUCCESS, $code);

		$messages = $mailer->getMessages();
		$message = array_shift($messages);
		self::assertNotNull($message);

		self::assertSame(
			[
				'ray.tomlinson@arpa.net' => null,
			],
			$message->getHeader('To'),
		);
		self::assertSame(
			[
				'from@example.org' => null,
			],
			$message->getHeader('From'),
		);
		self::assertSame(
			'Mailer test',
			$message->getHeader('Subject'),
		);
		self::assertSame(
			'Example message',
			$message->getHtmlBody(),
		);
		self::assertSame(
			'Example message',
			$message->getBody(),
		);
	}

	public function testOptions(): void
	{
		$mailer = new ToArrayMailer();

		$command = new MailerTestCommand($mailer);
		$tester = new CommandTester($command);

		$code = $tester->execute(
			[
				'to' => 'ray.tomlinson@arpa.net',
				'--from' => 'ray.tomlinson@arpa.net',
				'--subject' => 'First email ever',
				'--body' => '<h2>Hello world!</h2>',
			],
		);

		self::assertSame(
			<<<'MSG'
Mail sent.

MSG,
			$tester->getDisplay(),
		);
		self::assertSame($command::SUCCESS, $code);

		$messages = $mailer->getMessages();
		$message = array_shift($messages);
		self::assertNotNull($message);

		self::assertSame(
			[
				'ray.tomlinson@arpa.net' => null,
			],
			$message->getHeader('To'),
		);
		self::assertSame(
			[
				'ray.tomlinson@arpa.net' => null,
			],
			$message->getHeader('From'),
		);
		self::assertSame(
			'First email ever',
			$message->getHeader('Subject'),
		);
		self::assertSame(
			'<h2>Hello world!</h2>',
			$message->getHtmlBody(),
		);
		self::assertSame(
			'Hello world!',
			$message->getBody(),
		);
	}

	public function testRequest(): void
	{
		$request = new Request(new UrlScript('https://orisai.dev/foo/bar'));
		$mailer = new ToArrayMailer();

		$command = new MailerTestCommand($mailer, $request);
		$tester = new CommandTester($command);

		$code = $tester->execute(
			[
				'to' => 'hello.world@orisai.dev',
			],
		);

		self::assertSame(
			<<<'MSG'
Mail sent.

MSG,
			$tester->getDisplay(),
		);
		self::assertSame($command::SUCCESS, $code);

		$messages = $mailer->getMessages();
		$message = array_shift($messages);
		self::assertNotNull($message);

		self::assertSame(
			[
				'from@orisai.dev' => null,
			],
			$message->getHeader('From'),
		);
	}

}
