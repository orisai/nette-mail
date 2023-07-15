<?php declare(strict_types = 1);

namespace OriNette\Mail\Mailer;

use DateTimeInterface;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Orisai\Clock\SystemClock;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message as ExceptionMessage;
use Psr\Clock\ClockInterface;
use function assert;
use function serialize;
use function unserialize;

final class TracyPanelMailer implements Mailer
{

	private ?string $tempDir;

	private ClockInterface $clock;

	private ?DateTimeInterface $autoCleanupBefore = null;

	/** @var array<string, Message> */
	private array $messages = [];

	public function __construct(
		?string $tempDir = null,
		?ClockInterface $clock = null
	)
	{
		if ($tempDir !== null) {
			FileSystem::createDir($tempDir);
		}

		$this->tempDir = $tempDir;
		$this->clock = $clock ?? new SystemClock();
	}

	public function send(Message $mail): void
	{
		if ($this->autoCleanupBefore !== null) {
			$this->cleanup($this->autoCleanupBefore);
			$this->autoCleanupBefore = null;
		}

		$time = $this->clock->now()->format('Y-m-d_H-i-s-u');
		$id = "$time.data";
		$builtMessage = $mail->build();

		if ($this->tempDir === null) {
			$this->messages[$id] = $builtMessage;

			return;
		}

		$file = "$this->tempDir/$id";
		FileSystem::write($file, serialize($builtMessage));
	}

	public function isPersistent(): bool
	{
		return $this->tempDir !== null;
	}

	public function setAutoCleanup(DateTimeInterface $before): void
	{
		if ($before->getTimestamp() >= $this->clock->now()->getTimestamp()) {
			$message = ExceptionMessage::create()
				->withContext('Setting stored mails auto-cleanup.')
				->withProblem("Time '{$before->format('Y-m-d H:i:s')}' is in the future.")
				->withSolution('Set time which is in the past.')
				->with('Tip', 'Always use relative time.');

			throw InvalidState::create()
				->withMessage($message);
		}

		$this->autoCleanupBefore = $before;
	}

	public function cleanup(DateTimeInterface $before): void
	{
		if ($this->tempDir === null) {
			return;
		}

		$finder = $this->createFinder($this->tempDir);

		foreach ($finder as $file) {
			if ($file->getMTime() > $before->getTimestamp()) {
				/** @infection-ignore-all break also works because files are always sorted from oldest */
				continue;
			}

			FileSystem::delete($file->getPathname());
		}
	}

	/**
	 * @return array<string, Message>
	 */
	public function getMessages(): array
	{
		if ($this->tempDir === null) {
			return $this->messages;
		}

		$messages = [];
		foreach ($this->getFiles() as $id => $file) {
			$message = unserialize(FileSystem::read($file));
			assert($message instanceof Message);

			$messages[$id] = $message;
		}

		return $messages;
	}

	public function getMessage(string $id): ?Message
	{
		$message = $this->messages[$id] ?? null;
		if ($message !== null) {
			return $message;
		}

		$files = $this->getFiles();
		$file = $files[$id] ?? null;

		if ($file === null) {
			return null;
		}

		$message = unserialize(FileSystem::read($files[$id]));
		assert($message instanceof Message);

		return $message;
	}

	public function deleteById(string $id): void
	{
		unset($this->messages[$id]);

		$files = $this->getFiles();
		if (!isset($files[$id])) {
			return;
		}

		FileSystem::delete($files[$id]);
	}

	public function deleteAll(): void
	{
		$this->messages = [];
		foreach ($this->getFiles() as $file) {
			FileSystem::delete($file);
		}
	}

	/**
	 * @return array<string, string>
	 */
	public function getFiles(): array
	{
		if ($this->tempDir === null) {
			return [];
		}

		$files = [];
		foreach ($this->createFinder($this->tempDir) as $file) {
			$id = $file->getFilename();
			$files[$id] = $file->getPathname();
		}

		return $files;
	}

	private function createFinder(string $tempDir): Finder
	{
		return Finder::findFiles('*.data')->in($tempDir);
	}

}
