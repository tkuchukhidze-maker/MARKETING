<?php

namespace App\Support;

/**
 * Central dictionary for statuses, platforms, roles and contact types:
 * Georgian labels plus the colours used on the board.
 */
final class Studio
{
    /** Status key => [label, background, text colour]. Order = board order. */
    public const STATUSES = [
        'todo'        => ['გასაკეთებელი',   '#676879', '#FFFFFF'],
        'in_progress' => ['მუშავდება',      '#1F76C2', '#FFFFFF'],
        'review'      => ['დასადასტურებელი', '#FFCB00', '#323338'],
        'revision'    => ['შესწორება',      '#D83A52', '#FFFFFF'],
        'approved'    => ['დადასტურებული',  '#00854D', '#FFFFFF'],
        'published'   => ['გამოქვეყნდა',    '#6E3FD1', '#FFFFFF'],
    ];

    /** Statuses only a manager/admin may set. */
    public const APPROVAL_STATUSES = ['approved', 'revision'];

    public const PLATFORMS = [
        'facebook'  => ['Facebook',  '#1464D2', '#FFFFFF'],
        'instagram' => ['Instagram', '#C1307E', '#FFFFFF'],
        'story'     => ['Story',     '#FFB020', '#323338'],
    ];

    /** Format choices offered per platform. */
    public const FORMATS = [
        'facebook'  => ['პოსტი', 'პოსტერი', 'კარუსელი', 'ვიდეო', 'Reels', 'ქავერი', 'ივენთის ქავერი'],
        'instagram' => ['პოსტი 1:1', 'პოსტი 4:5', 'კარუსელი', 'Reels', 'ქავერი (Highlights)'],
        'story'     => ['სთორი 9:16', 'ვიდეო სთორი', 'ანიმირებული სთორი', 'სთორის სერია'],
    ];

    public const ROLES = [
        'admin'        => 'ადმინისტრატორი',
        'manager'      => 'მარკეტინგის ხელმძღვანელი',
        'smm'          => 'SMM მენეჯერი',
        'designer'     => 'დიზაინერი',
        'copywriter'   => 'კოპირაიტერი',
        'photographer' => 'ფოტოგრაფი',
    ];

    public const CONTACT_TYPES = [
        'artist'     => 'შემსრულებელი',
        'influencer' => 'ინფლუენსერი',
        'vendor'     => 'მომწოდებელი',
        'partner'    => 'პარტნიორი',
    ];

    public const AVATAR_COLORS = ['#6E3FD1', '#D83A52', '#00854D', '#1F76C2', '#B4400A', '#0B7A83', '#C1307E'];

    public static function statusLabel(string $key): string
    {
        return self::STATUSES[$key][0] ?? $key;
    }

    public static function platformLabel(string $key): string
    {
        return self::PLATFORMS[$key][0] ?? $key;
    }

    /** Inline style for a coloured status/platform cell. */
    public static function statusStyle(string $key): string
    {
        [, $bg, $fg] = self::STATUSES[$key] ?? self::STATUSES['todo'];

        return "background:{$bg};color:{$fg}";
    }

    public static function platformStyle(string $key): string
    {
        [, $bg, $fg] = self::PLATFORMS[$key] ?? self::PLATFORMS['facebook'];

        return "background:{$bg};color:{$fg}";
    }
}
