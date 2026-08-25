<?php

namespace Tests\Unit;

use App\Services\MessageSplitter;
use PHPUnit\Framework\TestCase;

class MessageSplitterTest extends TestCase
{
    private MessageSplitter $splitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->splitter = new MessageSplitter;
    }

    public function test_short_text_is_returned_as_single_chunk(): void
    {
        $text = 'Hello, support.';

        $this->assertSame([$text], $this->splitter->splitMessage($text));
        $this->assertFalse($this->splitter->needsSplitting($text));
    }

    public function test_text_at_the_limit_is_not_split(): void
    {
        $text = str_repeat('a', MessageSplitter::MAX_MESSAGE_LENGTH);

        $this->assertCount(1, $this->splitter->splitMessage($text));
    }

    public function test_multiline_text_is_split_into_chunks_within_the_limit(): void
    {
        $text = implode("\n", array_fill(0, 200, str_repeat('a', 100)));

        $chunks = $this->splitter->splitMessage($text);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(MessageSplitter::MAX_MESSAGE_LENGTH, mb_strlen($chunk));
        }
    }

    public function test_single_overlong_line_is_split_by_words(): void
    {
        $text = trim(str_repeat('word ', 2000));

        $chunks = $this->splitter->splitMessage($text);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(MessageSplitter::MAX_MESSAGE_LENGTH, mb_strlen($chunk));
            $this->assertStringNotContainsString('wordword', $chunk);
        }
    }

    public function test_length_is_measured_in_characters_not_bytes(): void
    {
        $text = str_repeat('Привет', 500);

        $this->assertSame(3000, mb_strlen($text));
        $this->assertGreaterThan(MessageSplitter::MAX_MESSAGE_LENGTH, strlen($text));
        $this->assertCount(1, $this->splitter->splitMessage($text));
    }
}
