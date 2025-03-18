<?php

class MarkdownConverter {
    private function convertHeaders($markdown, $style = 'jira') {
        if ($style === 'bold') {
            // Convert headers to *[Title]* format with asterisks
            $markdown = preg_replace('/^# (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^## (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^### (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^#### (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^##### (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^###### (.*?)$/m', '*[$1]*', $markdown);
        } else {
            // Convert headers to h1., h2. format
            $markdown = preg_replace('/^# (.*?)$/m', 'h1. $1', $markdown);
            $markdown = preg_replace('/^## (.*?)$/m', 'h2. $1', $markdown);
            $markdown = preg_replace('/^### (.*?)$/m', 'h3. $1', $markdown);
            $markdown = preg_replace('/^#### (.*?)$/m', 'h4. $1', $markdown);
            $markdown = preg_replace('/^##### (.*?)$/m', 'h5. $1', $markdown);
            $markdown = preg_replace('/^###### (.*?)$/m', 'h6. $1', $markdown);
        }
        return $markdown;
    }

    private function convertInlineCode($markdown) {
        return preg_replace('/`([^`]+)`/', "'$1'", $markdown);
    }

    private function convertBold($markdown) {
        return preg_replace('/\*\*(.*?)\*\*/', '*$1*', $markdown);
    }

    private function convertItalic($markdown) {
        // 이탤릭 변환을 별도로 처리하지 않음 (볼드와 충돌 방지)
        return $markdown;
    }

    private function convertLinks($markdown) {
        return preg_replace('/\[(.*?)\]\((.*?)\)/', '[$1|$2]', $markdown);
    }

    private function convertImages($markdown) {
        return preg_replace('/!\[(.*?)\]\((.*?)\)/', '!$2!', $markdown);
    }

    private function convertLists($markdown) {
        // Convert unordered lists
        $markdown = preg_replace('/^- (.*?)$/m', '* $1', $markdown);
        
        // Convert ordered lists
        $markdown = preg_replace('/^\d+\. (.*?)$/m', '# $1', $markdown);
        
        // Convert nested lists
        $markdown = preg_replace('/^  \* (.*?)$/m', '** $1', $markdown);
        $markdown = preg_replace('/^  # (.*?)$/m', '## $1', $markdown);
        
        return $markdown;
    }

    private function convertBlockquotes($markdown) {
        $lines = explode("\n", $markdown);
        $inQuote = false;
        $result = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^> (.*)$/', $line, $matches)) {
                if (!$inQuote) {
                    $result[] = '{quote}' . $matches[1];
                    $inQuote = true;
                } else {
                    $result[] = $matches[1];
                }
            } else {
                if ($inQuote) {
                    $result[count($result) - 1] .= '{quote}';
                    $inQuote = false;
                }
                $result[] = $line;
            }
        }
        
        if ($inQuote) {
            $result[count($result) - 1] .= '{quote}';
        }
        
        return implode("\n", $result);
    }

    private function convertCodeBlocks($markdown) {
        return preg_replace_callback('/```(.*?)\n(.*?)```/s', function($matches) {
            $lang = trim($matches[1]);
            $code = trim($matches[2]);
            
            if ($lang) {
                return "{code:$lang}\n$code\n{code}";
            }
            return "{code}\n$code\n{code}";
        }, $markdown);
    }

    public function toJira($markdown, $headerStyle = 'jira') {
        $result = $markdown;
        
        // Convert code blocks first to prevent interference
        $result = $this->convertCodeBlocks($result);
        
        // Convert other elements
        $result = $this->convertHeaders($result, $headerStyle);
        $result = $this->convertInlineCode($result);
        $result = $this->convertBold($result);
        $result = $this->convertItalic($result);
        $result = $this->convertLinks($result);
        $result = $this->convertImages($result);
        $result = $this->convertLists($result);
        $result = $this->convertBlockquotes($result);
        
        return $result;
    }

    public function toSlack($markdown) {
        $result = $markdown;
        
        // Convert code blocks first to prevent interference
        $result = preg_replace_callback('/```(.*?)\n(.*?)```/s', function($matches) {
            $code = trim($matches[2]);
            return "```\n$code\n```";
        }, $result);
        
        // Convert inline code (preserve backticks as per Slack format)
        $result = preg_replace('/`([^`]+)`/', '`$1`', $result);
        
        // Convert headers to bold
        $result = preg_replace('/^# (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^## (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^### (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^#### (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^##### (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^###### (.*?)$/m', '*$1*', $result);
        
        // Convert bold (** to *)
        $result = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $result);
        
        // Convert italic (_ to _)
        $result = preg_replace('/_(.*?)_/', '_$1_', $result);
        
        // Convert links to Slack format
        $result = preg_replace('/\[(.*?)\]\((.*?)\)/', '<$2|$1>', $result);
        
        // Convert images to just URLs
        $result = preg_replace('/!\[(.*?)\]\((.*?)\)/', '$2', $result);
        
        // Convert unordered lists
        $result = preg_replace('/^- (.*?)$/m', '• $1', $result);
        
        // Convert ordered lists (keep numbers)
        $result = preg_replace('/^(\d+)\. (.*?)$/m', '$1. $2', $result);
        
        // Convert blockquotes
        $result = preg_replace('/^> (.*?)$/m', '> $1', $result);
        
        // Clean up any extra newlines
        $result = preg_replace('/\n{3,}/', "\n\n", $result);
        
        return $result;
    }
} 