<?php

namespace Tests\Unit;

use App\Reporting\AnalysisMarkdown;
use PHPUnit\Framework\TestCase;

class AnalysisMarkdownTest extends TestCase
{
    public function test_checklist_is_split_out_with_groups(): void
    {
        $r = AnalysisMarkdown::split("\xEF\xBB\xBF# T\r\n\r\nIntro.\r\n\r\n## სამოქმედო ჩეკლისტი\r\n\r\n### დღეს\r\n\r\n- [ ] a\r\n- [x] b\r\n\r\n### 2 კვირაში\r\n\r\n[ ] c\r\n");

        $this->assertSame("# T\n\nIntro.", $r['body']);
        $this->assertSame('Intro.', $r['summary']);
        $this->assertSame([
            ['group' => 'დღეს', 'text' => 'a', 'done' => false],
            ['group' => 'დღეს', 'text' => 'b', 'done' => true],
            ['group' => '2 კვირაში', 'text' => 'c', 'done' => false],
        ], $r['actions']);
    }

    public function test_label_lines_group_items_and_sections_with_text_stay(): void
    {
        $r = AnalysisMarkdown::split("## გეგმა\n\nტექსტი რჩება.\n\n**რეკლამა:**\n[ ] ერთი\n\n## ბოლო\n\nდასასრული");

        $this->assertSame('რეკლამა', $r['actions'][0]['group']);
        $this->assertStringContainsString('## გეგმა', $r['body']);
        $this->assertStringContainsString('ტექსტი რჩება.', $r['body']);
        $this->assertStringNotContainsString('რეკლამა:', $r['body']);
    }

    public function test_plain_text_without_checklist_is_left_alone(): void
    {
        $r = AnalysisMarkdown::split("სათაური\n\n| a | b |\n|---|---|\n| 1 | 2 |");

        $this->assertSame([], $r['actions']);
        $this->assertStringContainsString('| 1 | 2 |', $r['body']);
    }
}
