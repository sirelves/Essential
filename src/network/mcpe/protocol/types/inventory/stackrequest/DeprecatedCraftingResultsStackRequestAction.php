<?php

/*
 *
 *  _____                    _   _       _
 * | ____|___ ___  ___ _ __ | |_(_) __ _| |
 * |  _| / __/ __|/ _ \ '_ \| __| |/ _` | |
 * | |___\__ \__ \  __/ | | | |_| | (_| | |
 * |_____|___/___/\___|_| |_|\__|_|\__,_|_|
 *
 * Essential — PocketMine-MP Fork
 * Supported MCPE/Bedrock versions: 1.12, 1.16 - 1.26.x
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Essential Team
 * @link https://github.com/BakuTeam/Essential
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\GetTypeIdFromConstTrait;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use function count;

/**
 * Not clear what this is needed for, but it is very clearly marked as deprecated, so hopefully it'll go away before I
 * have to write a proper description for it.
 */
final class DeprecatedCraftingResultsStackRequestAction extends ItemStackRequestAction{
	use GetTypeIdFromConstTrait;

	public const ID = ItemStackRequestActionType::CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING;

	/**
	 * @param ItemStack[] $results
	 */
	public function __construct(
		private array $results,
		private int $iterations
	){}

	/** @return ItemStack[] */
	public function getResults() : array{ return $this->results; }

	public function getIterations() : int{ return $this->iterations; }

	public static function read(PacketSerializer $in) : self{
		$results = [];
		if($in->getProtocolId() >= \pocketmine\network\mcpe\protocol\ProtocolInfo::PROTOCOL_1_26_62){
			//26.60: StackRequestItem por identificador; a acao e so informativa, entao le e descarta
			for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
				$variant = $in->getUnsignedVarInt();
				$in->getByte(); //legacy variant
				if($variant === 1){
					$in->getString(); //identifier
					$in->getVarInt(); //meta
				}elseif($variant !== 0){
					throw new \pocketmine\network\mcpe\protocol\PacketDecodeException("Unknown stack request item descriptor $variant");
				}
				$in->getLShort(); //count
				$in->getUnsignedVarInt(); //block runtime id
				$in->getString(); //user data
			}
		}else{
			for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
				$results[] = $in->getItemStackWithoutStackId(false);
			}
		}
		$iterations = $in->getByte();
		return new self($results, $iterations);
	}

	public function write(PacketSerializer $out) : void{
		$out->putUnsignedVarInt(count($this->results));
		foreach($this->results as $result){
			$out->putItemStackWithoutStackId($result);
		}
		$out->putByte($this->iterations);
	}
}
