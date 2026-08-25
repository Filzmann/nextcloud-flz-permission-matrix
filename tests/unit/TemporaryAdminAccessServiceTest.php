<?php

declare(strict_types=1);

namespace Psr\Clock { interface ClockInterface { public function now(): \DateTimeImmutable; } }
namespace Psr\Log { interface LoggerInterface { public function emergency(string|\Stringable $message,array $context=[]):void;public function alert(string|\Stringable $message,array $context=[]):void;public function critical(string|\Stringable $message,array $context=[]):void;public function error(string|\Stringable $message,array $context=[]):void;public function warning(string|\Stringable $message,array $context=[]):void;public function notice(string|\Stringable $message,array $context=[]):void;public function info(string|\Stringable $message,array $context=[]):void;public function debug(string|\Stringable $message,array $context=[]):void;public function log($level,string|\Stringable $message,array $context=[]):void; } }
namespace OCP {
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isAdmin(string $uid): bool; }
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

    $actor = new class implements OCP\IUser { public function getUID(): string { return 'admin-operator'; } };
    $session = new class($actor) implements OCP\IUserSession { public function __construct(public ?OCP\IUser $user) {} public function getUser(): ?OCP\IUser { return $this->user; } };
    $groups = new class implements OCP\IGroupManager { public array $admins=['admin-operator','admin-target']; public function isAdmin(string $uid): bool { return in_array($uid,$this->admins,true); } };
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

    try { $service->activate('admin-target',1441); throw new RuntimeException('Mehr als 24 Stunden wurden akzeptiert.'); } catch (InvalidArgumentException) {}
    if ($repository->mutations!==0) throw new RuntimeException('Eine ungültige Dauer darf keine Historie verändern.');

    $grant=$service->activate('admin-target',1440);
    if ($grant['startsAt']->format(DATE_ATOM)!=='2026-08-25T10:00:00+00:00'||$grant['endsAt']->format(DATE_ATOM)!=='2026-08-26T10:00:00+00:00') throw new RuntimeException('Serverzeit oder 24-Stunden-Grenze ist fehlerhaft.');
    if (!$service->hasActiveGrant('admin-target')||$service->hasActiveGrant('admin-other')) throw new RuntimeException('Die Freigabe ist nicht UID-genau.');

    $groups->admins=['admin-operator'];
    if ($service->hasActiveGrant('admin-target')) throw new RuntimeException('Entzogener Nextcloud-Adminstatus muss die Freigabe sofort unwirksam machen.');
    $groups->admins=['admin-operator','admin-target'];
    if (!$service->revoke('admin-target')||$service->hasActiveGrant('admin-target')) throw new RuntimeException('Widerruf muss den aktiven Zeitraum beenden.');

    $session->user=new class implements OCP\IUser { public function getUID():string{return 'ordinary';} };
    $before=$repository->mutations;
    try { $service->activate('admin-target',60); throw new RuntimeException('Nicht-Admin durfte freigeben.'); } catch (RuntimeException $error) { if ($error->getMessage()==='Nicht-Admin durfte freigeben.') throw $error; }
    if ($repository->mutations!==$before) throw new RuntimeException('Abgewiesene Freigabe darf nichts persistieren.');

    echo "Permission Matrix temporary admin access service tests passed\n";
}


