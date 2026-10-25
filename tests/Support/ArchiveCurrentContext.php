<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests\Support;

/** Passive declaration only; this fixture never authorizes or runs a native update. */
final class ArchiveCurrentContext {
	public function begin_native_mutation(): void {}

	public function complete_native_mutation(): void {}
}
