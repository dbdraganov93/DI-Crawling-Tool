<?php
require_once APPLICATION_PATH . '/../vendor/autoload.php';

class Shopfully_Mapper_BrochureMapper
{
    public function toEntity(array $data): Shopfully_Entity_Brochure
    {
        $brochure = new Shopfully_Entity_Brochure();
        $brochure->setId($data['id'] ?? null);
        $brochure->setTitle($data['title'] ?? null);
        $brochure->setDescription($data['description'] ?? '');
        $brochure->setRetailerId($data['retailer_id'] ?? null);
        $brochure->setStartDate(new DateTime($data['start_date']) ?? null);
        $brochure->setEndDate(new DateTime($data['end_date']) ?? null);
        $brochure->setPublishAt(new DateTime($data['publish_at']) ?? null);
        $brochure->setUnpublishAt(new DateTime($data['unpublish_at']) ?? null);
        $brochure->setThumbUrl($data['thumb_url'] ?? null);
        $brochure->setPublicationUrl($data['publication_url'] ?? null);
        $brochure->setNotes($data['notes'] ?? null);
        $brochure->setType($data['type'] ?? null);
        $brochure->setSubType($data['sub_type'] ?? '');
        $brochure->setIsDraft($data['is_draft'] ?? false);
        $brochure->setIsPublished($data['is_published'] ?? false);
        $brochure->setIsPremium($data['is_premium'] ?? false);
        $brochure->setIsVisible($data['is_visible'] ?? false);
        $brochure->setIsCustomTagging($data['is_custom_tagging'] ?? false);
        $brochure->setTrackingUrl(is_array($data['settings']['tracking_url']) ? reset($data['settings']['tracking_url']) : $data['settings']['tracking_url'] ?? '');
        $brochure->setTrackingUrlClient($data['settings']['tracking_url_client'] ?? '');
        $brochure->setStoreCount($data['store_count'] ?? 0);
        $brochure->setIsActive($data['is_active'] ?? null);
        $brochure->setCreated(new DateTime($data['created']) ?? null);
        $brochure->setModified(new DateTime($data['modified']) ?? null);

        return $brochure;
    }
}
