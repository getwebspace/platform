<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class ModerationPrompt extends AbstractMcpPrompt
{
    public const NAME = 'moderate_content';
    public const TITLE = 'Moderate reviews and guestbook';
    public const DESCRIPTION = 'Review everything waiting for moderation: product / publication reviews, questions and guestbook entries.';
    public const TOOLS = ['review_list', 'review_moderate', 'guestbook_list', 'guestbook_moderate'];

    public function render(array $args = []): string
    {
        return "Help me moderate the site.\n\n"
            . "1. Call review_list and guestbook_list (the default is entries waiting for moderation).\n"
            . "2. For each entry show: what it is about, the author, the text, the rating. Propose a decision - publish or hide - with one line of reasoning (spam, insults, advertising, off-topic, fine).\n"
            . "3. For questions and polite negative reviews write a short draft of the public answer.\n"
            . "4. Apply decisions with review_moderate / guestbook_moderate only for the entries I confirm. An answer is public: show me the final text before sending it with the tool.\n"
            . $this->rules();
    }
}
