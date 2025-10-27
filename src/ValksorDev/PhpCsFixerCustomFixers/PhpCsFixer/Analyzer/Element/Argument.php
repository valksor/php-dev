<?php declare(strict_types = 1);

/*
 * This file is part of the Valksor package.
 *
 * (c) Dāvis Zālītis (k0d3r1s)
 * (c) SIA Valksor <packages@valksor.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Element;

/**
 * @internal
 */
final readonly class Argument
{
    public function __construct(
        private int $startIndex,
        private int $endIndex,
        private bool $isConstant,
    ) {
    }

    public function getEndIndex(): int
    {
        return $this->endIndex;
    }

    public function getIsConstant(): bool
    {
        return $this->isConstant;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }
}
