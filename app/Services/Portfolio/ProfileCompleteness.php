<?php

namespace App\Services\Portfolio;

use App\Models\Education;
use App\Models\Experience;
use App\Models\ExpertiseArea;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SocialLink;

class ProfileCompleteness
{
    /** @return array{percent: int, items: array<int, array{label: string, done: bool, link: string}>} */
    public function check(Profile $profile): array
    {
        $items = [
            ['Headline and introduction', filled($profile->headline) && filled($profile->intro), route('admin.profile.edit')],
            ['About me', filled($profile->about), route('admin.profile.edit')],
            ['Profile photo', (bool) $profile->avatarSrc(), route('admin.profile.edit')],
            ['Contact email', filled($profile->email), route('admin.profile.edit')],
            ['Resume / CV uploaded', (bool) $profile->resume, route('admin.profile.edit')],
            ['At least one published experience', Experience::published()->exists(), route('admin.resources.index', 'experiences')],
            ['At least two published projects', Project::published()->count() >= 2, route('admin.resources.index', 'projects')],
            ['A project with a screenshot', Project::published()->whereHas('media')->exists(), route('admin.resources.index', 'projects')],
            ['Five or more published skills', Skill::published()->count() >= 5, route('admin.resources.index', 'skills')],
            ['Skills with an honest proficiency rating', Skill::published()->whereNotNull('level')->exists(), route('admin.resources.index', 'skills')],
            ['Technical expertise areas', ExpertiseArea::published()->exists(), route('admin.resources.index', 'expertise')],
            ['Education', Education::published()->exists(), route('admin.resources.index', 'education')],
            ['GitHub or LinkedIn link', SocialLink::published()->exists(), route('admin.resources.index', 'links')],
            ['SEO description', filled($profile->meta_description), route('admin.profile.edit')],
        ];

        $items = array_map(fn ($i) => ['label' => $i[0], 'done' => $i[1], 'link' => $i[2]], $items);

        return [
            'percent' => (int) round(100 * count(array_filter($items, fn ($i) => $i['done'])) / count($items)),
            'items' => $items,
        ];
    }
}
