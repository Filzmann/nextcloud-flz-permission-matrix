<?php

declare(strict_types=1);

namespace Psr\Clock { interface ClockInterface { public function now(): \DateTimeImmutable; } }
namespace Psr\Log { interface LoggerInterface { public function emergency(string|\Stringable $message,array $context=[]):void;public function alert(string|\Stringable $message,array $context=[]):void;public function critical(string|\Stringable $message,array $context=[]):void;public function error(string|\Stringable $message,array $context=[]):void;public function warning(string|\Stringable $message,array $context=[]):void;public function notice(string|\Stringable $message,array $context=[]):void;public function info(string|\Stringable $message,array $context=[]):void;public function debug(string|\Stringable $message,array $context=[]):void;public function log($level,string|\Stringable $message,array $context=[]):void; } }
namespace OCP {
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isAdmin(string $uid): bool; public function isInGroup(string $uid,string $gid):bool; }
}
namespace OCP\AppFramework\Utility {
    interface ITimeFactory extends \Psr\Clock\ClockInterface { public function getTime(): int; public function getDateTime(string $time='now',?\DateTimeZone $timezone=null):\DateTime; public function withTimeZone(\DateTimeZone $timezone):static; public function getTimeZone(?string $timezone=null):\DateTimeZone; }
}
namespace OCA\FilzmannPermissionMatrix\Db {
    interface TemporaryAdminAccessRepositoryInterface {
        public function replaceActive(string $targetUid,string $grantedBy,\DateTimeImmutable $startsAt,\DateTimeImmutable $endsAt):array;
        public function revokeActive(string $targetUid,string $revokedBy,\DateTimeImmutable $revokedAt):bool;
        public function activeFor(string $targetUid,\DateTimeImmutable $at):?array;
        public function history():array;
    }
}
namespace {
    use OCA\FilzmannPermissionMatrix\Db\TemporaryAdminAccessRepositoryInterface;
    use OCA\FilzmannPermissionMatrix\Service\TemporaryAdminAccessService;

    $actor = new class implements OCP\IUser { public function getUID(): string { return 'privacy-officer'; } };
    $session = new class($actor) implements OCP\IUserSession { public function __construct(public ?OCP\IUser $user) {} public function getUser(): ?OCP\IUser { return $this->user; } };
    $groups = new class implements OCP\IGroupManager {
        public array $admins=['admin-operator','admin-target'];
        public array $privacyOfficers=['privacy-officer'];
        public function isAdmin(string $uid): bool { return in_array($uid,$this->admins,true); }
        public function isInGroup(string $uid,string $gid):bool { return $gid==='Datenschutzbeauftragte' && in_array($uid,$this->privacyOfficers,true); }
    };
    $clock = new class implements OCP\AppFramework\Utility\ITimeFactory {
        public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-25T10:00:00+00:00'); }
        public function getTime(): int { return $this->now()->getTimestamp(); }
        public function getDateTime(string $time='now',?DateTimeZone $timezone=null):DateTime { return new DateTime($time,$timezone); }
        public function withTimeZone(DateTimeZone $timezone):static { return $this; }
        public function getTimeZone(?string $timezone=null):DateTimeZone { return new DateTimeZone($timezone??'UTC'); }
    };
    $repository = new class implements TemporaryAdminAccessRepositoryInterface {
        public array $rows=[];
        public int $mutations=0;
        public function replaceActive(string $targetUid,string $grantedBy,DateTimeImmutable $startsAt,DateTimeImmutable $endsAt):array {
            $this->mutations++;
            foreach ($this->rows as &$row) if ($row['targetUid']===$targetUid && $row['revokedAt']===null) $row['revokedAt']=$startsAt;
            $row=['id'=>count($this->rows)+1,'targetUid'=>$targetUid,'grantedBy'=>$grantedBy,'startsAt'=>$startsAt,'endsAt'=>$endsAt,'revokedAt'=>null,'revokedBy'=>null];
            $this->rows[]=$row;
            return $row;
        }
        public function revokeActive(string $targetUid,string $revokedBy,DateTimeImmutable $revokedAt):bool { foreach($this->rows as &$row)if($row['targetUid']===$targetUid&&$row['revokedAt']===null){$row['revokedAt']=$revokedAt;$row['revokedBy']=$revokedBy;$this->mutations++;return true;}return false; }
        public function activeFor(string $targetUid,DateTimeImmutable $at):?array { foreach(array_reverse($this->rows) as $row)if($row['targetUid']===$targetUid&&$row['revokedAt']===null&&$row['startsAt']<=$at&&$row['endsAt']>$at)return $row;return null; }
        public function history():array { return array_reverse($this->rows); }
    };
    $logger = new class implements Psr\Log\LoggerInterface { public array $messages=[];public function emergency(string|Stringable $m,array $c=[]):void{}public function alert(string|Stringable $m,array $c=[]):void{}public function critical(string|Stringable $m,array $c=[]):void{}public function error(string|Stringable $m,array $c=[]):void{$this->messages[]=['error',(string)$m,$c];}public function warning(string|Stringable $m,array $c=[]):void{}public function notice(string|Stringable $m,array $c=[]):void{}public function info(string|Stringable $m,array $c=[]):void{$this->messages[]=['info',(string)$m,$c];}public function debug(string|Stringable $m,array $c=[]):void{}public function log($l,string|Stringable $m,array $c=[]):void{} };
    $service = new TemporaryAdminAccessService($session,$groups,$repository,$clock,$logger);

    if (!$service->canManage() || $service->currentAdminNeedsGrant()) throw new RuntimeException('Datenschutzbeauftragte ohne Adminstatus müssen die Freigaben verwalten können.');
    if ($service->state()['history'] !== []) throw new RuntimeException('Datenschutzbeauftragte müssen die Historie lesen können.');
    try { $service->activate('admin-target',1441); throw new RuntimeException('Mehr als 24 Stunden wurden akzeptiert.'); } catch (InvalidArgumentException) {}
    try { $service->activate('ordinary',60); throw new RuntimeException('Ein manipuliertes Nicht-Admin-Ziel wurde akzeptiert.'); } catch (InvalidArgumentException $error) { if ($error->getMessage()==='Ein manipuliertes Nicht-Admin-Ziel wurde akzeptiert.') throw $error; }
    try { $service->revoke('ordinary'); throw new RuntimeException('Ein manipuliertes Nicht-Admin-Ziel wurde widerrufen.'); } catch (InvalidArgumentException $error) { if ($error->getMessage()==='Ein manipuliertes Nicht-Admin-Ziel wurde widerrufen.') throw $error; }
    if ($repository->mutations!==0) throw new RuntimeException('Eine ungültige Dauer darf keine Historie verändern.');

    $grant=$service->activate('admin-target',1440);
    if ($grant['startsAt']->format(DATE_ATOM)!=='2026-08-25T10:00:00+00:00'||$grant['endsAt']->format(DATE_ATOM)!=='2026-08-26T10:00:00+00:00') throw new RuntimeException('Serverzeit oder 24-Stunden-Grenze ist fehlerhaft.');
    if (!$service->hasActiveGrant('admin-target')||$service->hasActiveGrant('admin-other')) throw new RuntimeException('Die Freigabe ist nicht UID-genau.');

    $groups->admins=['admin-operator'];
    if ($service->hasActiveGrant('admin-target')) throw new RuntimeException('Entzogener Nextcloud-Adminstatus muss die Freigabe sofort unwirksam machen.');
    $groups->admins=['admin-operator','admin-target'];
    $groups->privacyOfficers=[];
    $before=$repository->mutations;
    try { $service->revoke('admin-target'); throw new RuntimeException('Nach Rollenverlust durfte widerrufen werden.'); } catch (RuntimeException $error) { if ($error->getMessage()==='Nach Rollenverlust durfte widerrufen werden.') throw $error; }
    if ($repository->mutations!==$before) throw new RuntimeException('Rollenverlust muss ohne Mutation verweigern.');
    $groups->privacyOfficers=['privacy-officer'];
    if (!$service->revoke('admin-target')||$service->hasActiveGrant('admin-target')) throw new RuntimeException('Widerruf muss den aktiven Zeitraum beenden.');
    if (($repository->rows[0]['revokedBy'] ?? null)!=='privacy-officer') throw new RuntimeException('Die freigebende Datenschutzrolle muss auditierbar bleiben.');

    foreach (['admin-operator','ordinary'] as $deniedUid) {
        $session->user=new class($deniedUid) implements OCP\IUser { public function __construct(private string $uid){} public function getUID():string{return $this->uid;} };
        $before=$repository->mutations;
        if ($service->canManage()) throw new RuntimeException('Native Administration oder gewöhnliches Konto darf keine Freigaben verwalten.');
        foreach (['state','activate','revoke'] as $operation) {
            try {
                if ($operation==='state') $service->state();
                elseif ($operation==='activate') $service->activate('admin-target',60);
                else $service->revoke('admin-target');
                throw new RuntimeException('Unberechtigter Zugriff wurde akzeptiert: '.$operation);
            } catch (RuntimeException $error) {
                if (str_starts_with($error->getMessage(),'Unberechtigter Zugriff wurde akzeptiert:')) throw $error;
            }
        }
        if ($repository->mutations!==$before) throw new RuntimeException('Abgewiesene Aktionen dürfen nichts persistieren.');
    }

    $session->user=new class implements OCP\IUser { public function getUID():string{return 'admin-operator';} };
    if (!$service->currentAdminNeedsGrant()) throw new RuntimeException('Ein aktueller Admin ohne Freigabe benötigt den sicheren Hinweis.');
    $groups->privacyOfficers=['privacy-officer','admin-operator'];
    if (!$service->canManage() || !$service->currentAdminNeedsGrant()) throw new RuntimeException('Nur dasselbe kombinierte Admin-/Datenschutzkonto darf Link und Hinweis erhalten.');

    echo "Permission Matrix temporary admin access service tests passed\n";
}

