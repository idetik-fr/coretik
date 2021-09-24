<?php

namespace Coretik\Services\Forms;

interface ConfigInterface
{
    public function getTemplateDir(): string;
    public function setTemplateDir(string $templateDir): self;
    public function getFormFile(): string;
    public function setFormFile(string $formFile): self;
    public function getFormRulesFile(): string;
    public function setFormRulesFile(string $formRulesFile): self;
    public function getFormPrefix(): string;
    public function setFormPrefix(string $formPrefix): self;
    public function getCssErrorClass(): string;
    public function setCssErrorClass(string $cssErrorClass): self;
    public function locator(): LocatorInterface;
    public function setLocator(LocatorInterface $locator): self;
}
