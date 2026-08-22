<?php

namespace App\Services;

class MessageSplitter
{
    const MAX_MESSAGE_LENGTH = 4096;

    /**
     * Split long message into chunks
     */
    public function splitMessage(string $text, int $maxLength = self::MAX_MESSAGE_LENGTH): array
    {
        if (mb_strlen($text) <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        $currentChunk = '';
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            // If single line is too long, split it by words
            if (mb_strlen($line) > $maxLength) {
                $words = explode(' ', $line);
                foreach ($words as $word) {
                    if (mb_strlen($currentChunk.' '.$word) <= $maxLength) {
                        $currentChunk .= ($currentChunk ? ' ' : '').$word;
                    } else {
                        if ($currentChunk) {
                            $chunks[] = $currentChunk;
                        }
                        $currentChunk = $word;
                    }
                }

                continue;
            }

            // Try to add full line
            if (mb_strlen($currentChunk."\n".$line) <= $maxLength) {
                $currentChunk .= ($currentChunk ? "\n" : '').$line;
            } else {
                // Current chunk is full, start new one
                if ($currentChunk) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = $line;
            }
        }

        // Add last chunk
        if ($currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Check if text needs splitting
     */
    public function needsSplitting(string $text, int $maxLength = self::MAX_MESSAGE_LENGTH): bool
    {
        return mb_strlen($text) > $maxLength;
    }
}
