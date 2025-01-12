<?php

declare(strict_types=1);

namespace MediaWiki\Extension\MWEVESSO;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * Based on https://github.com/killmails/oauth2-eve/ and recreated in this project for security reasons
 */
class EveOnlineSSOResourceOwner implements ResourceOwnerInterface
{
    /**
     * Creates new resource owner.
     *
     * @param array $response
     *   The raw response
     * @param array $characterInfo Response from the /characters/ ESI endpoint
     *   The response from the /characters/ ESI endpoint
     */
    public function __construct(
        protected array $response,
        protected array $characterInfo
    ) {
    }

    /**
     * Get resource owner id (character id).
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        if (empty($this->response['CharacterID'])) {
            return null;
        }
        return intval($this->response['CharacterID']);
    }

    /**
     * Get character id. Alias of getId().
     *
     * @return int|null
     */
    public function getCharacterID(): ?int
    {
        return $this->getId();
    }

    /**
     * Get resource owner name (character name).
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        if (empty($this->response['CharacterName'])) {
            return null;
        }
        return strval($this->response['CharacterName']);
    }

    /**
     * Get character name. Alias of getName().
     *
     * @return string|null
     */
    public function getCharacterName(): ?string
    {
        return $this->getName();
    }

    /**
     * Get character owner hash.
     *
     * @return string|null
     */
    public function getCharacterOwnerHash(): ?string
    {
        if (empty($this->response['CharacterOwnerHash'])) {
            return null;
        }
        return strval($this->response['CharacterOwnerHash']);
    }

    /**
     * Get the ID of the Corporation that the Character currently belongs to
     *
     * @return int|null
     */
    public function getCorporationId(): ?int
    {
        if (empty($this->characterInfo['corporation_id'])) {
            return null;
        }
        return intval($this->characterInfo['corporation_id']);
    }

    /**
     * Get the ID of the Alliance that the Character currently belongs to
     *
     * @return int|null
     */
    public function getAllianceId(): ?int
    {
        if (empty($this->characterInfo['alliance_id'])) {
            return null;
        }
        return intval($this->characterInfo['alliance_id']);
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->response;
    }
}
