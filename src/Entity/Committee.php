<?php

namespace AppBundle\Entity;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use AppBundle\Exception\CommitteeAlreadyApprovedException;
use AppBundle\Exception\CommitteeAlreadyTreatedException;
use AppBundle\Geocoder\GeoPointInterface;
use AppBundle\ValueObject\Link;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use libphonenumber\PhoneNumber;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * This entity represents a committee group.
 *
 * @ORM\Table(
 *   name="committees",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(name="committee_uuid_unique", columns="uuid"),
 *     @ORM\UniqueConstraint(name="committee_canonical_name_unique", columns="canonical_name"),
 *     @ORM\UniqueConstraint(name="committee_slug_unique", columns="slug")
 *   },
 *   indexes={
 *     @ORM\Index(name="committee_status_idx", columns="status")
 *   }
 * )
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CommitteeRepository")
 *
 * @Algolia\Index(autoIndex=false)
 */
class Committee implements GeoPointInterface
{
    const APPROVED = 'APPROVED';
    const PRE_APPROVED = 'PRE_APPROVED';
    const PENDING = 'PENDING';
    const REFUSED = 'REFUSED';
    const PRE_REFUSED = 'PRE_REFUSED';

    use EntityIdentityTrait;
    use EntityCrudTrait;
    use EntityPostAddressTrait;
    use EntityTimestampableTrait;

    /**
     * The committee name.
     *
     * @ORM\Column
     *
     * @Algolia\Attribute
     */
    private $name;

    /**
     * The committee name.
     *
     * @ORM\Column
     *
     * @Algolia\Attribute
     */
    private $canonicalName;

    /**
     * The committee slug.
     *
     * @ORM\Column
     *
     * @Gedmo\Slug(fields={"canonicalName"})
     *
     * @Algolia\Attribute
     */
    private $slug;

    /**
     * The committee description.
     *
     * @ORM\Column(type="text")
     *
     * @Algolia\Attribute
     */
    private $description;

    /**
     * The committee Facebook page URL.
     *
     * @ORM\Column(nullable=true)
     */
    private $facebookPageUrl;

    /**
     * The committee Twitter nickname.
     *
     * @ORM\Column(nullable=true)
     */
    private $twitterNickname;

    /**
     * The committee Google+ page URL.
     *
     * @ORM\Column(nullable=true)
     */
    private $googlePlusPageUrl;

    /**
     * The committee current status.
     *
     * @ORM\Column(length=20)
     */
    private $status;

    /**
     * The timestamp when an administrator approved this committee.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $approvedAt;

    /**
     * The timestamp when an administrator refused this committee.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $refusedAt;

    /**
     * The adherent UUID who created this committee.
     *
     * @ORM\Column(type="uuid", nullable=true)
     */
    private $createdBy;

    /**
     * @ORM\Column(type="phone_number", nullable=true)
     */
    private $phone;

    /**
     * The cached number of members (followers and hosts).
     *
     * @ORM\Column(type="smallint", options={"unsigned": true})
     *
     * @Algolia\Attribute
     */
    private $membersCounts;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $adminComment;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $coordinatorComment;

    /**
     * A cached list of the hosts (for admin).
     */
    public $hosts = [];

    public $creator;

    public function __construct(
        UuidInterface $uuid,
        UuidInterface $creator,
        string $name,
        string $description,
        PostAddress $address,
        PhoneNumber $phone = null,
        string $slug = null,
        string $status = self::PENDING,
        string $approvedAt = null,
        string $createdAt = 'now',
        int $membersCount = 0
    ) {
        if ($approvedAt) {
            $approvedAt = new \DateTime($approvedAt);
        }

        if ($createdAt) {
            $createdAt = new \DateTime($createdAt);
        }

        $this->uuid = $uuid;
        $this->createdBy = $creator;
        $this->setName($name);
        $this->slug = $slug;
        $this->description = $description;
        $this->postAddress = $address;
        $this->phone = $phone;
        $this->status = $status;
        $this->membersCounts = $membersCount;
        $this->approvedAt = $approvedAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function __toString()
    {
        return $this->name ?: '';
    }

    public static function createSimple(UuidInterface $uuid, string $creatorUuid, string $name, string $description, PostAddress $address, PhoneNumber $phone, string $createdAt = 'now'): self
    {
        $committee = new self(
            $uuid,
            Uuid::fromString($creatorUuid),
            $name,
            $description,
            $address,
            $phone
        );
        $committee->createdAt = new \DateTime($createdAt);

        return $committee;
    }

    public static function createForAdherent(Adherent $adherent, string $name, string $description, PostAddress $address, PhoneNumber $phone, string $createdAt = 'now'): self
    {
        $committee = new self(
            self::createUuid($name),
            clone $adherent->getUuid(),
            $name,
            $description,
            $address,
            $phone
        );
        $committee->createdAt = new \DateTime($createdAt);

        return $committee;
    }

    public static function createUuid(string $name): UuidInterface
    {
        return Uuid::uuid5(Uuid::NAMESPACE_OID, static::canonicalize($name));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getPostAddress(): PostAddress
    {
        return $this->postAddress;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getFacebookPageUrl(): ?string
    {
        return $this->facebookPageUrl;
    }

    public function getTwitterNickname(): ?string
    {
        return $this->twitterNickname;
    }

    public function getGooglePlusPageUrl(): ?string
    {
        return $this->googlePlusPageUrl;
    }

    public function getAdminComment(): ?string
    {
        return $this->adminComment;
    }

    public function setAdminComment(string $adminComment): void
    {
        $this->adminComment = $adminComment;
    }

    public function getCoordinatorComment(): ?string
    {
        return $this->coordinatorComment;
    }

    public function setCoordinatorComment(string $coordinatorComment = null): void
    {
        $this->coordinatorComment = $coordinatorComment;
    }

    public function isWaitingForApproval(): bool
    {
        return self::PENDING === $this->status && !$this->approvedAt;
    }

    /**
     * @Algolia\IndexIf
     */
    public function isApproved(): bool
    {
        return self::APPROVED === $this->status && $this->approvedAt;
    }

    public function isPending(): bool
    {
        return self::PENDING === $this->status;
    }

    public function isPreApproved(): bool
    {
        return self::PRE_APPROVED === $this->status;
    }

    public function isPreRefused(): bool
    {
        return self::PRE_REFUSED === $this->status;
    }

    public function isRefused(): bool
    {
        return self::REFUSED === $this->status;
    }

    public function getMembersCount(): int
    {
        return $this->membersCounts;
    }

    public function incrementMembersCount(int $increment = 1)
    {
        $this->membersCounts += $increment;
    }

    public function decrementMembersCount(int $increment = 1)
    {
        $this->membersCounts -= $increment;
    }

    /**
     * Marks this committee as pre-approved.
     */
    public function preApproved()
    {
        if ($this->isApproved() || $this->isRefused()) {
            throw new CommitteeAlreadyTreatedException($this->uuid);
        }

        $this->status = self::PRE_APPROVED;
    }

    /**
     * Marks this committee as approved.
     *
     * @param string $timestamp
     */
    public function approved(string $timestamp = 'now')
    {
        if ($this->isApproved()) {
            throw new CommitteeAlreadyApprovedException($this->uuid);
        }

        $this->status = self::APPROVED;
        $this->approvedAt = new \DateTime($timestamp);
        $this->refusedAt = null;
    }

    /**
     * Marks this committee as pre-refused.
     */
    public function preRefused()
    {
        if ($this->isApproved() || $this->isRefused()) {
            throw new CommitteeAlreadyTreatedException($this->uuid);
        }

        $this->status = self::PRE_REFUSED;
    }

    /**
     * Marks this committee as refused/rejected.
     *
     * @param string $timestamp
     */
    public function refused(string $timestamp = 'now')
    {
        $this->status = self::REFUSED;
        $this->refusedAt = new \DateTime($timestamp);
        $this->approvedAt = null;
    }

    public function setSocialNetworks(
        string $facebookPageUrl = null,
        string $twitterNickname = null,
        string $googlePlusPageUrl = null
    ) {
        $this->facebookPageUrl = $facebookPageUrl;
        $this->setTwitterNickname($twitterNickname);
        $this->googlePlusPageUrl = $googlePlusPageUrl;
    }

    public function setFacebookPageUrl($facebookPageUrl)
    {
        $this->facebookPageUrl = $facebookPageUrl;
    }

    public function setTwitterNickname($twitterNickname)
    {
        $this->twitterNickname = ltrim((string) $twitterNickname, '@');
    }

    public function setGooglePlusPageUrl($googlePlusPageUrl)
    {
        $this->googlePlusPageUrl = $googlePlusPageUrl;
    }

    public function setName(string $name)
    {
        $this->name = $name;
        $this->canonicalName = static::canonicalize($name);
    }

    public static function canonicalize(string $name): string
    {
        return mb_strtolower($name);
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy->toString();
    }

    public function isCreatedBy(UuidInterface $uuid): bool
    {
        return $this->createdBy && $this->createdBy->equals($uuid);
    }

    public function setCreator(?Adherent $creator): void
    {
        $this->creator = $creator;
    }

    public function getCreator(): ?Adherent
    {
        return $this->creator;
    }

    public function setPhone(PhoneNumber $phone = null): void
    {
        $this->phone = $phone;
    }

    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    /**
     * Returns the approval date and time.
     *
     * @return \DateTime|null
     */
    public function getApprovedAt(): ?\DateTime
    {
        return $this->approvedAt;
    }

    public function updateSlug(string $slug)
    {
        $this->slug = $slug;
    }

    /**
     * Returns the list of social networks links.
     *
     * @return Link[]
     */
    public function getSocialNetworksLinks(): array
    {
        $links = [];

        if ($this->facebookPageUrl) {
            $links['facebook'] = $this->createLink($this->facebookPageUrl, 'Facebook');
        }

        if ($this->googlePlusPageUrl) {
            $links['google_plus'] = $this->createLink($this->googlePlusPageUrl, 'Google +');
        }

        if ($this->twitterNickname) {
            $links['twitter'] = $this->createLink(sprintf('https://twitter.com/%s', $this->twitterNickname), 'Twitter');
        }

        return $links;
    }

    private function createLink(string $url, string $label): Link
    {
        return new Link($url, $label);
    }

    public function equals(self $other): bool
    {
        return $this->uuid->equals($other->getUuid());
    }

    public function update(string $name, string $description, PostAddress $address, PhoneNumber $phone)
    {
        $this->setName($name);
        $this->description = $description;

        if (!$this->postAddress->equals($address)) {
            $this->postAddress = $address;
        }

        if (null === $this->phone || !$this->phone->equals($phone)) {
            $this->phone = $phone;
        }
    }
}
