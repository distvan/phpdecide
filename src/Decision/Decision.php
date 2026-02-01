<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use DateTimeImmutable;

final class Decision
{
    public function __construct(
        private DecisionId $id,
        private string $title,
        private DecisionStatus $status,
        private DateTimeImmutable $date,
        private Scope $scope,
        private DecisionContent $content,
        private Examples $examples,
        private ?Rules $rules,
        private ?AiMetadata $aiMetadata = null,
        private ?References $references = null
    ) {}

    public function id(): DecisionId
    {
        return $this->id;
    }
    public function title(): string
    {
        return $this->title;
    }
    public function status(): DecisionStatus
    {
        return $this->status;
    }
    public function date(): DateTimeImmutable
    {
        return $this->date;
    }
    public function scope(): Scope
    {
        return $this->scope;
    }
    public function content(): DecisionContent
    {
        return $this->content;
    }
    public function examples(): Examples
    {
        return $this->examples;
    }
    public function rules(): ?Rules
    {
        return $this->rules;
    }
    public function aiMetadata(): ?AiMetadata
    {
        return $this->aiMetadata;
    }
    public function references(): ?References
    {
        return $this->references;
    }
}
