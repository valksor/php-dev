<?php declare(strict_types = 1);

/*
 * This file is part of the Valksor package.
 *
 * (c) Davis Zalitis (k0d3r1s)
 * (c) SIA Valksor <packages@valksor.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Element;

/**
 * @internal
 */
final readonly class CaseElement
{
    public function __construct(
        private int $colonIndex,
    ) {
    }

    public function getColonIndex(): int
    {
        return $this->colonIndex;
    }
}
