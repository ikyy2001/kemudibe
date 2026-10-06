<?php

namespace App\Services;

use App\Models\Institution;

class InstitutionContext
{
    protected ?Institution $institution = null;
    protected bool $bypass = false;

    /**
     * Set the current active institution
     */
    public function set(?Institution $institution): void
    {
        $this->institution = $institution;
    }

    /**
     * Get the current active institution
     */
    public function get(): ?Institution
    {
        return $this->institution;
    }

    /**
     * Get the current active institution (alias of get())
     */
    public function getInstitution(): ?Institution
    {
        return $this->institution;
    }

    /**
     * Get current institution ID
     */
    public function getId(): ?int
    {
        return $this->institution?->id;
    }

    /**
     * Check if context has an active institution
     */
    public function hasInstitution(): bool
    {
        return $this->institution !== null;
    }

    /**
     * Check if current institution is read-only (suspended or expired)
     */
    public function isReadOnly(): bool
    {
        return $this->institution !== null && $this->institution->isReadOnly();
    }

    /**
     * Set bypass mode (e.g. for superadmin or seeders)
     */
    public function setBypass(bool $bypass): void
    {
        $this->bypass = $bypass;
    }

    /**
     * Check if tenancy scope is bypassed
     */
    public function isBypassed(): bool
    {
        return $this->bypass;
    }

    /**
     * Reset context to clean state
     */
    public function reset(): void
    {
        $this->institution = null;
        $this->bypass = false;
    }
}
