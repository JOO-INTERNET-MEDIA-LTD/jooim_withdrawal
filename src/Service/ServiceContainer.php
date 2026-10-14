<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository\WithdrawalRepository;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\CsrfService;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService;

final class ServiceContainer
{
    private static array $instances = [];
    private \Module $module;
    private \Context $context;
    private array $services = [];

    private function __construct(\Module $module)
    {
        $this->module = $module;
        $this->context = \Context::getContext();
    }

    public static function get(\Module $module): self
    {
        $key = spl_object_hash($module);
        return self::$instances[$key] ??= new self($module);
    }

    public function config(): ModuleConfig { return $this->services[__FUNCTION__] ??= new ModuleConfig($this->context); }
    public function tokens(): TokenService { return $this->services[__FUNCTION__] ??= new TokenService(); }
    public function csrf(): CsrfService { return $this->services[__FUNCTION__] ??= new CsrfService($this->context, $this->tokens()); }
    public function normalizer(): OrderReferenceNormalizer { return $this->services[__FUNCTION__] ??= new OrderReferenceNormalizer(); }
    public function orderLookup(): OrderLookupService { return $this->services[__FUNCTION__] ??= new OrderLookupService($this->context, $this->normalizer()); }
    public function identities(): OrderIdentityService { return $this->services[__FUNCTION__] ??= new OrderIdentityService($this->context); }
    public function rateLimit(): RateLimitService { return $this->services[__FUNCTION__] ??= new RateLimitService($this->context, $this->config(), $this->tokens()); }
    public function guestSessions(): GuestSessionService { return $this->services[__FUNCTION__] ??= new GuestSessionService($this->context, $this->config(), $this->tokens()); }
    public function accessResolver(): AccessResolver { return $this->services[__FUNCTION__] ??= new AccessResolver($this->context, $this->guestSessions()); }
    public function withdrawalRepository(): WithdrawalRepository { return $this->services[__FUNCTION__] ??= new WithdrawalRepository(); }
    public function storage(): StorageService { return $this->services[__FUNCTION__] ??= new StorageService(); }
    public function pdf(): WithdrawalPdfService { return $this->services[__FUNCTION__] ??= new WithdrawalPdfService($this->context, $this->module, $this->storage()); }
    public function mail(): WithdrawalMailService { return $this->services[__FUNCTION__] ??= new WithdrawalMailService($this->context, $this->module, $this->config()); }
    public function guestAccess(): GuestAccessService { return $this->services[__FUNCTION__] ??= new GuestAccessService($this->context, $this->module, $this->config(), $this->tokens(), $this->orderLookup(), $this->identities(), $this->rateLimit(), $this->guestSessions(), $this->mail()); }
    public function quantities(): WithdrawalQuantityCalculator { return $this->services[__FUNCTION__] ??= new WithdrawalQuantityCalculator(); }
    public function period(): WithdrawalPeriodEvaluator { return $this->services[__FUNCTION__] ??= new WithdrawalPeriodEvaluator(); }
    public function references(): WithdrawalReferenceGenerator { return $this->services[__FUNCTION__] ??= new WithdrawalReferenceGenerator($this->config()); }
    public function drafts(): WithdrawalDraftService { return $this->services[__FUNCTION__] ??= new WithdrawalDraftService($this->context, $this->config(), $this->tokens(), $this->orderLookup(), $this->identities(), $this->quantities()); }
    public function withdrawals(): WithdrawalSubmissionService { return $this->services[__FUNCTION__] ??= new WithdrawalSubmissionService($this->context, $this->module, $this->config(), $this->tokens(), $this->withdrawalRepository(), $this->quantities(), $this->period(), $this->references(), $this->pdf(), $this->mail()); }
    public function recovery(): WithdrawalRecoveryService { return $this->services[__FUNCTION__] ??= new WithdrawalRecoveryService($this->context, $this->config(), $this->withdrawalRepository(), $this->pdf()); }
    public function maintenance(): WithdrawalMaintenanceService { return $this->services[__FUNCTION__] ??= new WithdrawalMaintenanceService($this->context, $this->config(), $this->withdrawalRepository(), $this->mail(), $this->storage(), $this->recovery()); }
}
