<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class WritePublicationPrompt extends AbstractMcpPrompt
{
    public const NAME = 'write_publication';
    public const TITLE = 'Write a publication';
    public const DESCRIPTION = 'Write a news item or an article and save it as a draft, publish only after approval.';
    public const TOOLS = ['publication_list', 'publication_create', 'publication_update'];

    public function getArguments(): array
    {
        return [
            ['name' => 'topic', 'description' => 'What to write about', 'required' => true],
            ['name' => 'category', 'description' => 'Publication category title, if known', 'required' => false],
        ];
    }

    public function render(array $args = []): string
    {
        $topic = trim((string) ($args['topic'] ?? ''));
        $category = trim((string) ($args['category'] ?? ''));

        if ($topic === '') {
            throw new \InvalidArgumentException('Argument topic is required');
        }

        return "Write a publication for the site on the topic: {$topic}.\n\n"
            . "1. Call publication_list to see the existing publications: their categories, titles and how long and in what tone they are. Do not repeat a topic that already exists.\n"
            . ($category !== '' ? "2. The category is \"{$category}\" - take its uuid from the items of publication_list. If you cannot find it, ask me.\n" : "2. Ask me which category to use if it is not obvious from publication_list.\n")
            . "3. Draft: a title, a short text for the list (1-2 sentences) and the full text in simple HTML (p, h2, ul, strong). Add a meta description up to 160 characters. Show me the draft first.\n"
            . "4. After my approval save it with publication_create - it stays a DRAFT and is not visible on the site.\n"
            . "5. Publish with publication_update publish=true (or publish_at for a date) only when I say so explicitly.\n"
            . $this->rules();
    }
}
