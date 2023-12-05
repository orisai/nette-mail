<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Mailer;

use DateTimeImmutable;
use Nette\Mail\Message;
use OriNette\Mail\Mailer\FileMailer;
use Orisai\Clock\FrozenClock;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;
use function array_pop;
use function basename;
use function file_get_contents;
use function sleep;
use function str_replace;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

final class FileMailerTest extends TestCase
{

	public function testSend(): void
	{
		$path = VFS::register() . '://path';

		$mailer = new FileMailer($path);
		$this->assertFiles($mailer, 0);

		$message = new Message();

		$mailer->send($message);
		$this->assertFiles($mailer, 1);

		$mailer->send($message);
		$this->assertFiles($mailer, 2);
	}

	public function testCleanup(): void
	{
		$path = VFS::register() . '://path';
		$mailer = new FileMailer($path);
		$message = new Message();

		$mailer->send($message);
		$this->assertFiles($mailer, 1);

		// VFS does not use clock, so it's not really useful here
		$before2 = new DateTimeImmutable();
		sleep(1);
		$mailer->send($message);
		$this->assertFiles($mailer, 2);

		$mailer->cleanup($before2);
		$this->assertFiles($mailer, 1);
	}

	public function testAutoCleanup(): void
	{
		$path = VFS::register() . '://path';
		$mailer = new FileMailer($path);
		$message = new Message();

		$mailer->send($message);
		$mailer->send($message);
		$this->assertFiles($mailer, 2);

		// VFS does not use clock, so it's not really useful here
		sleep(1);
		$before2 = new DateTimeImmutable('-1 second');
		sleep(1);
		$mailer->send($message);
		$this->assertFiles($mailer, 3);

		// Cleanup triggers after next sent message
		$mailer->setAutoCleanup($before2);
		$this->assertFiles($mailer, 3);

		$mailer->send($message);
		$mailer->send($message);
		$this->assertFiles($mailer, 3);
	}

	public function testAutoCleanupInFuture(): void
	{
		$path = VFS::register() . '://path';
		$clock = new FrozenClock(1);
		$mailer = new FileMailer($path, $clock);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(
			<<<'MSG'
Context: Setting stored mails auto-cleanup.
Problem: Time '1970-01-01 00:00:02' is in the future.
Solution: Set time which is in the past.
Tip: Always use relative time.
MSG,
		);

		$mailer->setAutoCleanup($clock->now()->modify('+1 second'));
	}

	public function testAutoCleanupInPresent(): void
	{
		$path = VFS::register() . '://path';
		$clock = new FrozenClock(1);
		$mailer = new FileMailer($path, $clock);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(
			<<<'MSG'
Context: Setting stored mails auto-cleanup.
Problem: Time '1970-01-01 00:00:01' is in the future.
Solution: Set time which is in the past.
Tip: Always use relative time.
MSG,
		);

		$mailer->setAutoCleanup($clock->now());
	}

	public function testFileContent(): void
	{
		$path = VFS::register() . '://path';
		$mailer = new FileMailer($path);

		$message = new Message();
		$message->setHeader('Message-ID', 'test');
		$message->setHeader('Date', null);
		$message->setBody('body');

		$mailer->send($message);

		$files = $mailer->getFiles();
		$file = array_pop($files);

		self::assertSame(
			<<<'MSG'
MIME-Version: 1.0
X-Mailer: Nette Framework
Message-ID: test
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit

body
MSG,
			str_replace("\r\n", PHP_EOL, file_get_contents($file)),
		);
	}

	public function testFileName(): void
	{
		$path = VFS::register() . '://path';
		$clock = new FrozenClock(1);

		$mailer = new FileMailer($path, $clock);
		$message = new Message();

		$mailer->send($message);

		$clock->sleep(1);
		$mailer->send($message);

		$ds = DIRECTORY_SEPARATOR;
		self::assertSame(
			[
				'1970-01-01_00-00-01-000000.eml' => "$path{$ds}1970-01-01_00-00-01-000000.eml",
				'1970-01-01_00-00-02-000000.eml' => "$path{$ds}1970-01-01_00-00-02-000000.eml",
			],
			$mailer->getFiles(),
		);
	}

	private function assertFiles(FileMailer $mailer, int $expectedCount): void
	{
		$files = $mailer->getFiles();
		self::assertCount($expectedCount, $files);

		foreach ($files as $name => $file) {
			self::assertFileExists($file);
			self::assertSame(basename($file), $name);
			self::assertMatchesRegularExpression(
				'/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}-\d{6}\.eml$/',
				$name,
			);
		}
	}

}
