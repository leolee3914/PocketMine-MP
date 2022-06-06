<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\ItemSerializer;
use pocketmine\data\bedrock\item\ItemTypeSerializeException;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;

/**
 * This class handles translation between network item ID+metadata to PocketMine-MP internal ID+metadata and vice versa.
 */
final class ItemTranslator{
	public const NO_BLOCK_RUNTIME_ID = 0;

	use SingletonTrait;

	private static function make() : self{
		return new self(GlobalItemTypeDictionary::getInstance()->getDictionary(), new ItemSerializer(), new ItemDeserializer());
	}

	public function __construct(
		private ItemTypeDictionary $dictionary,
		private ItemSerializer $itemSerializer,
		private ItemDeserializer $itemDeserializer
	){}

	/**
	 * @return int[]|null
	 * @phpstan-return array{int, int, int}|null
	 */
	public function toNetworkIdQuiet(Item $item) : ?array{
		try{
			return $this->toNetworkId($item);
		}catch(ItemTypeSerializeException){
			return null;
		}
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int, int}
	 *
	 * @throws ItemTypeSerializeException
	 */
	public function toNetworkId(Item $item) : array{
		//TODO: we should probably come up with a cache for this

		$itemData = $this->itemSerializer->serialize($item);

		$numericId = $this->dictionary->fromStringId($itemData->getName());
		$blockStateData = $itemData->getBlock();

		if($blockStateData !== null){
			$blockRuntimeId = RuntimeBlockMapping::getInstance()->getBlockStateDictionary()->lookupStateIdFromData($blockStateData);
			if($blockRuntimeId === null){
				throw new AssumptionFailedError("Unmapped blockstate returned by blockstate serializer: " . $blockStateData->toNbt());
			}
		}else{
			$blockRuntimeId = self::NO_BLOCK_RUNTIME_ID; //this is technically a valid block runtime ID, but is used to represent "no block" (derp mojang)
		}

		return [$numericId, $itemData->getMeta(), $blockRuntimeId];
	}

	/**
	 * @throws TypeConversionException
	 */
	public function fromNetworkId(int $networkId, int $networkMeta, int $networkBlockRuntimeId) : Item{
		$stringId = $this->dictionary->fromIntId($networkId);

		$blockStateData = $networkBlockRuntimeId !== self::NO_BLOCK_RUNTIME_ID ?
			RuntimeBlockMapping::getInstance()->getBlockStateDictionary()->getDataFromStateId($networkBlockRuntimeId) :
			null;

		return $this->itemDeserializer->deserialize(new SavedItemData($stringId, $networkMeta, $blockStateData));
	}
}
