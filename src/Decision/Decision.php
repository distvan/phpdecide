<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use DateTimeImmutable;

final class Decision
{
    public function __construct(
        private readonly DecisionId $id,
        private readonly string $title,
        private readonly DecisionStatus $status,
        private readonly DateTimeImmutable $date,
        private readonly Scope $scope,
        private readonly DecisionContent $content,
        private readonly Examples $examples,
        private readonly ?Rules $rules,
        private readonly ?AiMetadata $aiMetadata = null,
        private readonly ?References $references = null
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
    public function isActive(): bool
    {
        return $this->status->isActive();
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
