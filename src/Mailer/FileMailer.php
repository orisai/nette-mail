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

final class FileMailer implements Mailer
{

	private string $dir;

	private ClockInterface $clock;

	private ?DateTimeInterface $autoCleanupBefore = null;

	public function __construct(string $dir, ?ClockInterface $clock = null)
	{
		FileSystem::createDir($dir);
		$this->dir = $dir;
		$this->clock = $clock ?? new SystemClock();
	}

	public function send(Message $mail): void
	{
		if ($this->autoCleanupBefore !== null) {
			$this->cleanup($this->autoCleanupBefore);
			$this->autoCleanupBefore = null;
		}

		$time = $this->clock->now()->format('Y-m-d_H-i-s-u');
		$file = "$this->dir/$time.eml";
		FileSystem::write($file, $mail->generateMessage());
	}

	/**
	 * @return array<string, string>
	 */
	public function getFiles(): array
	{
		$files = [];
		foreach ($this->createFinder($this->dir) as $file) {
			$id = $file->getFilename();
			$files[$id] = $file->getPathname();
		}

		return $files;
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
		$finder = $this->createFinder($this->dir);

		foreach ($finder as $file) {
			if ($file->getMTime() > $before->getTimestamp()) {
				/** @infection-ignore-all break also works because files are always sorted from oldest */
				continue;
			}

			FileSystem::delete($file->getPathname());
		}
	}

	private function createFinder(string $dir): Finder
	{
		return Finder::findFiles('*.eml')->in($dir);
	}

}
