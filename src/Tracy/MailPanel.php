<?php declare(strict_types = 1);

namespace OriNette\Mail\Tracy;

use Closure;
use Latte\Engine;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\RequestFactory;
use Nette\Http\Response;
use Nette\Mail\MimePart;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use OriNette\Http\Tester\TestResponse;
use OriNette\Mail\Mailer\TracyPanelMailer;
use ReflectionProperty;
use Tracy\Debugger;
use Tracy\IBarPanel;
use function assert;
use function is_string;
use function str_starts_with;

final class MailPanel implements IBarPanel
{

	private const SignalName = 'orisai-mail-panel';

	private TracyPanelMailer $mailer;

	private IRequest $request;

	private IResponse $response;

	private ?string $tempDir;

	public function __construct(
		TracyPanelMailer $mailer,
		?IRequest $request = null,
		?IResponse $response = null,
		?string $tempDir = null
	)
	{
		$this->mailer = $mailer;
		$this->request = $request ?? (new RequestFactory())->fromGlobals();
		$this->response = $response ?? new Response();
		$this->tempDir = $tempDir;

		$this->processSignal();
	}

	public function getTab(): string
	{
		return $this->renderTemplate(__DIR__ . '/MailPanel.tab.latte');
	}

	public function getPanel(): string
	{
		return $this->renderTemplate(__DIR__ . '/MailPanel.panel.latte');
	}

	private function renderTemplate(string $file): string
	{
		return $this->getLatte()->renderToString(
			$file,
			new MailPanelTemplate(
				$this->mailer->getMessages(),
				$this->mailer->isPersistent(),
				$this->getCreateLinkCb(),
				$this->getPlainTextCb(),
				$this->getAttachmentLabelCb(),
			),
		);
	}

	private function getLatte(): Engine
	{
		$latte = new Engine();
		/** @infection-ignore-all Optional, but helpful */
		$latte->setTempDirectory($this->tempDir);

		return $latte;
	}

	/**
	 * @return Closure(array<string, string>): string
	 */
	private function getCreateLinkCb(): Closure
	{
		return fn (array $params = []): string => $this->createLink($params);
	}

	/**
	 * @param array<string, string> $params
	 */
	private function createLink(array $params = []): string
	{
		$url = $this->request->getUrl();
		$url = $url->withQueryParameter('do', self::SignalName);
		foreach ($params as $name => $value) {
			$url = $url->withQueryParameter($name, $value);
		}

		return $url->getAbsoluteUrl();
	}

	/**
	 * @return Closure(MimePart): string
	 */
	private function getPlainTextCb(): Closure
	{
		return function (MimePart $part): string {
			$reflector = new ReflectionProperty(MimePart::class, 'parts');
			/** @infection-ignore-all Not needed since PHP 8.1 */
			$reflector->setAccessible(true);

			return $this->getPlainText($part, $reflector) ?? $part->getBody();
		};
	}

	private function getPlainText(MimePart $part, ReflectionProperty $reflector): ?string
	{
		/** @infection-ignore-all I just don't care enough to test it */
		foreach ($reflector->getValue($part) as $subPart) {
			assert($subPart instanceof MimePart);

			$contentType = $subPart->getHeader('Content-Type');
			assert(is_string($contentType));

			if (
				str_starts_with($contentType, 'text/plain')
				&& $subPart->getHeader('Content-Transfer-Encoding') !== 'base64'
			) {
				return $subPart->getBody();
			}

			if (str_starts_with($contentType, 'multipart/alternative')) {
				$subPartBody = $this->getPlainText($subPart, $reflector);
				if ($subPartBody !== null) {
					return $subPartBody;
				}
			}
		}

		return null;
	}

	/**
	 * @return Closure(MimePart): string
	 */
	private function getAttachmentLabelCb(): Closure
	{
		return static function (MimePart $attachment): string {
			$contentDisposition = $attachment->getHeader('Content-Disposition');
			$contentType = $attachment->getHeader('Content-Type');
			$matches = Strings::match($contentDisposition, '#filename="(.+?)"#');

			/** @infection-ignore-all */
			return ($matches !== null ? "$matches[1] " : '') . "($contentType)";
		};
	}

	private function processSignal(): void
	{
		if (Debugger::$productionMode !== Debugger::Development) {
			return;
		}

		if ($this->request->getQuery('do') !== self::SignalName) {
			return;
		}

		$action = $this->request->getQuery('orisai-action');
		$messageId = $this->request->getQuery('orisai-id');
		$attachmentId = $this->request->getQuery('orisai-attachment-id');

		if ($action === 'detail' && is_string($messageId)) {
			$this->renderDetail($messageId);
		} elseif ($action === 'source' && is_string($messageId)) {
			$this->renderSource($messageId);
		} elseif ($action === 'attachment' && is_string($messageId) && Validators::isNumericInt($attachmentId)) {
			$this->renderAttachment($messageId, (int) $attachmentId);
		} elseif ($action === 'delete' && is_string($messageId)) {
			$this->deleteById($messageId);
		} elseif ($action === 'delete') {
			$this->deleteAll();
		}
	}

	private function renderDetail(string $messageId): void
	{
		$message = $this->mailer->getMessage($messageId);
		if ($message === null) {
			$this->redirectBack();
		}

		$this->response->setContentType('text/html');
		$this->getLatte()->render(__DIR__ . '/MailPanel.body.latte', [
			'message' => $message,
			'getPlainText' => $this->getPlainTextCb(),
		]);
		$this->sendResponse();
	}

	private function renderSource(string $messageId): void
	{
		$message = $this->mailer->getMessage($messageId);
		if ($message === null) {
			$this->redirectBack();
		}

		$this->response->setContentType('text/plain');
		echo $message->getEncodedMessage();
		$this->sendResponse();
	}

	private function renderAttachment(string $messageId, int $attachmentId): void
	{
		$message = $this->mailer->getMessage($messageId);
		if ($message === null) {
			$this->redirectBack();
		}

		$attachment = $message->getAttachments()[$attachmentId] ?? null;
		if ($attachment === null) {
			$this->redirectBack();
		}

		$contentType = $attachment->getHeader('Content-Type');
		assert(is_string($contentType));

		$this->response->setContentType($contentType);
		echo $attachment->getBody();
		$this->sendResponse();
	}

	private function deleteById(string $id): void
	{
		$this->mailer->deleteById($id);
		$this->redirectBack();
	}

	private function deleteAll(): void
	{
		$this->mailer->deleteAll();
		$this->redirectBack();
	}

	/**
	 * @return never
	 */
	private function redirectBack(): void
	{
		$url = $this->request->getUrl();

		$query = $url->getQueryParameters();
		unset($query['do'], $query['orisai-action'], $query['orisai-id'], $query['orisai-attachment-id']);
		$url = $url->withQuery($query);

		$this->response->redirect($url->getAbsoluteUrl());
		$this->sendResponse();
	}

	/**
	 * @return never
	 */
	private function sendResponse(): void
	{
		/** @infection-ignore-all */
		if ($this->response instanceof TestResponse) {
			throw MailPanelRequestTermination::create();
		}

		exit; // @codeCoverageIgnore
	}

}
