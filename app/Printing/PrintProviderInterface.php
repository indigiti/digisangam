<?php
declare(strict_types=1);
namespace DigiSangam\Printing;
interface PrintProviderInterface { public function send(array $job,string $document): array; }
