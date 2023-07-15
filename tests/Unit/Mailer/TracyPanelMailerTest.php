<?php declare(strict_types = 1);

namespace Tests\OriNette\Mail\Unit\Mailer;

use DateTimeImmutable;
use Generator;
use Nette\Mail\Message;
use OriNette\Mail\Mailer\TracyPanelMailer;
use Orisai\Clock\FrozenClock;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;
use function array_key_first;
use function array_keys;
use function array_shift;
use function basename;
use function count;
use function sleep;
use const DIRECTORY_SEPARATOR;

final class TracyPanelMailerTest extends TestCase
{

	public function testIsPersistent(): void
	{
		$mailer = new TracyPanelMailer(null);
		self::assertFalse($mailer->isPersistent());

		$mailer = new TracyPanelMailer(VFS::register() . '://path');
		self::assertTrue($mailer->isPersistent());
	}

	/**
	 * @dataProvider providePath
	 */
	public function testSend(?string $path): void
	{
		$mailer = new TracyPanelMailer($path);

		$this->assertFiles($mailer, 0);
		$this->assertMessages($mailer, []);

		$message1 = $this->createMessage();
		$mailer->send($message1);
		$this->assertMessages($mailer, [$message1]);
		if ($path !== null) {
			$this->assertFiles($mailer, 1);
		}

		$message2 = $this->createMessage();
		$mailer->send($message2);
		$this->assertMessages($mailer, [$message1, $message2]);
		if ($path !== null) {
			$this->assertFiles($mailer, 2);
		}
	}

	/**
	 * @dataProvider providePath
	 */
	public function testMessageIsBuilt(?string $path): void
	{
		$mailer = new TracyPanelMailer($path);

		$message = $this->createMessage();
		$mailer->send($message);

		$messages = $mailer->getMessages();
		$sentMessage = array_shift($messages);

		self::assertNull($message->getHeader('Content-Type'));
		self::assertNotNull($sentMessage);
		self::assertNotNull($sentMessage->getHeader('Content-Type'));
	}

	public function testCleanup(): void
	{
		$path = VFS::register() . '://path';

		$mailer = new TracyPanelMailer($path);
		$message = $this->createMessage();

		$mailer->send($message);
		$this->assertFiles($mailer, 1);
		self::assertCount(1, $mailer->getMessages());

		// VFS does not use clock, so it's not really useful here
		$before2 = new DateTimeImmutable();
		sleep(1);
		$mailer->send($message);
		$this->assertFiles($mailer, 2);
		self::assertCount(2, $mailer->getMessages());

		$mailer->cleanup($before2);
		$this->assertFiles($mailer, 1);
		self::assertCount(1, $mailer->getMessages());
	}

	/**
	 * Differs from persistent version because it is simply not implemented and not needed
	 */
	public function testCleanupNonPersistent(): void
	{
		$mailer = new TracyPanelMailer(null);
		$message = $this->createMessage();

		$mailer->send($message);
		self::assertCount(1, $mailer->getMessages());

		// VFS does not use clock, so it's not really useful here
		$before2 = new DateTimeImmutable();
		sleep(1);
		$mailer->send($message);
		self::assertCount(2, $mailer->getMessages());

		$mailer->cleanup($before2);
		self::assertCount(2, $mailer->getMessages());
	}

	public function testAutoCleanup(): void
	{
		$path = VFS::register() . '://path';

		$mailer = new TracyPanelMailer($path);
		$message = $this->createMessage();

		$mailer->send($message);
		$mailer->send($message);
		$this->assertFiles($mailer, 2);
		self::assertCount(2, $mailer->getMessages());

		// VFS does not use clock, so it's not really useful here
		sleep(1);
		$before2 = new DateTimeImmutable('-1 second');
		sleep(1);
		$mailer->send($message);
		$this->assertFiles($mailer, 3);
		self::assertCount(3, $mailer->getMessages());

		// Cleanup triggers after next sent message
		$mailer->setAutoCleanup($before2);
		$this->assertFiles($mailer, 3);
		self::assertCount(3, $mailer->getMessages());

		$mailer->send($message);
		$mailer->send($message);
		$this->assertFiles($mailer, 3);
		self::assertCount(3, $mailer->getMessages());
	}

	public function testAutoCleanupInFuture(): void
	{
		$path = VFS::register() . '://path';
		$clock = new FrozenClock(1);
		$mailer = new TracyPanelMailer($path, $clock);

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
		$mailer = new TracyPanelMailer($path, $clock);

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

	/**
	 * @dataProvider providePath
	 */
	public function testFileName(?string $path): void
	{
		$clock = new FrozenClock(1);
		$mailer = new TracyPanelMailer($path, $clock);
		$message = $this->createMessage();

		$mailer->send($message);

		$clock->move(1);
		$mailer->send($message);

		$ds = DIRECTORY_SEPARATOR;
		$expectedFiles = [
			'1970-01-01_00-00-01-000000.data' => "$path{$ds}1970-01-01_00-00-01-000000.data",
			'1970-01-01_00-00-02-000000.data' => "$path{$ds}1970-01-01_00-00-02-000000.data",
		];

		if ($path !== null) {
			self::assertSame(
				$expectedFiles,
				$mailer->getFiles(),
			);
		} else {
			self::assertSame(
				[],
				$mailer->getFiles(),
			);
		}

		self::assertSame(
			array_keys($expectedFiles),
			array_keys($mailer->getMessages()),
		);
	}

	/**
	 * @dataProvider providePath
	 */
	public function testDeleteAll(?string $path): void
	{
		$mailer = new TracyPanelMailer($path);

		$message1 = $this->createMessage();
		$mailer->send($message1);

		$message2 = $this->createMessage();
		$mailer->send($message2);

		$this->assertMessages($mailer, [$message1, $message2]);

		$mailer->deleteAll();
		$this->assertMessages($mailer, []);
	}

	/**
	 * @dataProvider providePath
	 */
	public function testDeleteById(?string $path): void
	{
		$mailer = new TracyPanelMailer($path);

		$message1 = $this->createMessage();
		$mailer->send($message1);

		$message2 = $this->createMessage();
		$mailer->send($message2);

		$this->assertMessages($mailer, [$message1, $message2]);

		$id1 = array_key_first($mailer->getMessages());
		self::assertNotNull($id1);

		$mailer->deleteById($id1);

		$this->assertMessages($mailer, [$message2]);
	}

	/**
	 * @dataProvider providePath
	 */
	public function testGetById(?string $path): void
	{
		$mailer = new TracyPanelMailer($path);

		$message1 = $this->createMessage();
		$mailer->send($message1);

		$message2 = $this->createMessage();
		$mailer->send($message2);

		$id1 = array_key_first($mailer->getMessages());
		self::assertNotNull($id1);

		$sentMessage1 = $mailer->getMessage($id1);
		self::assertNotNull($sentMessage1);

		$this->removeDynamicMessageParts($sentMessage1);
		self::assertEquals($message1, $sentMessage1);

		$nonExistentMessage = $mailer->getMessage('non-existent');
		self::assertNull($nonExistentMessage);
	}

	public function providePath(): Generator
	{
		yield [VFS::register() . '://path'];
		yield [null];
	}

	private function createMessage(): Message
	{
		static $i = 0;

		$message = new Message();
		$message->setHeader('Message-ID', (string) $i);
		$i++;

		return $message;
	}

	private function removeDynamicMessageParts(Message $message): void
	{
		$message->setHeader('Content-Type', null);
		$message->setHeader('Content-Transfer-Encoding', null);
	}

	/**
	 * @param list<Message> $expectedMessages
	 */
	private function assertMessages(TracyPanelMailer $mailer, array $expectedMessages): void
	{
		$mailerMessages = $mailer->getMessages();
		self::assertCount(count($expectedMessages), $mailerMessages);

		foreach ($expectedMessages as $expectedMessage) {
			$actualMessage = array_shift($mailerMessages);
			self::assertNotNull($actualMessage);

			$this->removeDynamicMessageParts($expectedMessage);
			$this->removeDynamicMessageParts($actualMessage);

			self::assertEquals($expectedMessage, $actualMessage);
		}
	}

	private function assertFiles(TracyPanelMailer $mailer, int $expectedCount): void
	{
		$files = $mailer->getFiles();

		self::assertCount($expectedCount, $files);

		foreach ($files as $name => $file) {
			self::assertFileExists($file);
			self::assertSame(basename($file), $name);
			self::assertMatchesRegularExpression(
				'/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}-\d{6}\.data$/',
				$name,
			);
		}
	}

}
