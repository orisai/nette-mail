<?php declare(strict_types = 1);

namespace OriNette\Mail\Tracy;

use Closure;
use Nette\Mail\Message;
use Nette\Mail\MimePart;

final class MailPanelTemplate
{

	/** @var array<int|string, Message> */
	public array $messages;

	public bool $isPersistent;

	/** @var Closure(array<string, string>): string */
	public Closure $createLink;

	/** @var Closure(MimePart): string */
	public Closure $getPlainText;

	/** @var Closure(MimePart): string */
	public Closure $getAttachmentLabel;

	/**
	 * @param array<int|string, Message>             $messages
	 * @param Closure(array<string, string>): string $createLink
	 * @param Closure(MimePart): string              $getPlainText
	 * @param Closure(MimePart): string              $getAttachmentLabel
	 */
	public function __construct(
		array $messages,
		bool $isPersistent,
		Closure $createLink,
		Closure $getPlainText,
		Closure $getAttachmentLabel
	)
	{
		$this->messages = $messages;
		$this->isPersistent = $isPersistent;
		$this->createLink = $createLink;
		$this->getPlainText = $getPlainText;
		$this->getAttachmentLabel = $getAttachmentLabel;
	}

}
