<?php

namespace App\Admin;

use App\Admin\Resources\AchievementResource;
use App\Admin\Resources\CertificationResource;
use App\Admin\Resources\EducationResource;
use App\Admin\Resources\ExperienceResource;
use App\Admin\Resources\ExpertiseAreaResource;
use App\Admin\Resources\PostResource;
use App\Admin\Resources\ProjectResource;
use App\Admin\Resources\SkillCategoryResource;
use App\Admin\Resources\SkillResource;
use App\Admin\Resources\SocialLinkResource;
use App\Admin\Resources\TestimonialResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Resources
{
    /** Sidebar order. @var array<int, class-string<Resource>> */
    public const ALL = [
        ExperienceResource::class,
        ProjectResource::class,
        AchievementResource::class,
        SkillCategoryResource::class,
        SkillResource::class,
        ExpertiseAreaResource::class,
        EducationResource::class,
        CertificationResource::class,
        SocialLinkResource::class,
        PostResource::class,
        TestimonialResource::class,
    ];

    /** Resources the conversation importer may propose changes to. @return array<int, class-string<Resource>> */
    public static function importable(): array
    {
        return array_values(array_filter(self::ALL, fn ($r) => $r::IMPORTABLE));
    }

    /** @return class-string<Resource> */
    public static function bySlug(string $slug): string
    {
        foreach (self::ALL as $resource) {
            if ($resource::SLUG === $slug) {
                return $resource;
            }
        }
        throw new NotFoundHttpException;
    }

    /** @return class-string<Resource>|null */
    public static function byKey(string $key): ?string
    {
        foreach (self::ALL as $resource) {
            if ($resource::KEY === $key) {
                return $resource;
            }
        }

        return null;
    }
}
