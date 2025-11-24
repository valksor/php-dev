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

namespace TestProject;

use Exception;

use function array_filter;
use function count;
use function in_array;

/**
 * Sample PHP class for testing snapshot functionality.
 *
 * This is a demonstration class that shows different types of PHP code
 * that should be included in snapshots.
 */
final class SampleProject
{
    /**
     * Constructor for the sample class.
     *
     * @param array $items   Initial items to add
     * @param bool  $enabled Whether the project is enabled
     */
    public function __construct(
        private array $items = [],
        private bool $enabled = false,
    ) {
    }

    /**
     * Add an item to the collection.
     *
     * @param string $item The item to add
     *
     * @throws Exception When item is empty
     */
    public function addItem(
        string $item,
    ): void {
        if ('' === $item) {
            throw new Exception('Item cannot be empty');
        }

        if (!in_array($item, $this->items, true)) {
            $this->items[] = $item;
        }
    }

    /**
     * Get all items that match a filter.
     *
     * @param callable $filter Filter function
     *
     * @return array Filtered items
     */
    public function getFilteredItems(
        callable $filter,
    ): array {
        return array_filter($this->items, $filter);
    }

    /**
     * Get count of all items.
     *
     * @return int Number of items
     */
    public function getItemCount(): int
    {
        return count($this->items);
    }

    /**
     * Check if the project is enabled.
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable or disable the project.
     *
     * @param bool $enabled New enabled state
     */
    public function setEnabled(
        bool $enabled,
    ): void {
        $this->enabled = $enabled;
    }
}
