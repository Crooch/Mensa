<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\Container;
use App\Core\Request;
final class AutonomiaController
{
 public function __construct(private Container $c) {}
 public function verifica(Request $r,array $u): array { return $this->c->autonomia()->verificaFamiglia($u,(int)$r->parametro('id')); }
 public function disattiva(Request $r,array $u): array { return $this->c->autonomia()->disattiva($u,(int)$r->parametro('id'),$r->corpo()); }
 public function disattivaBambino(Request $r,array $u): array { return $this->c->autonomia()->disattivaBambino($u,(int)$r->parametro('id'),$r->corpo()); }
 public function nome(Request $r,array $u): array { return $this->c->autonomia()->modificaBambino($u,(int)$r->parametro('id'),$r->corpo()); }
 public function configurazione(Request $r,array $u): array { return $this->c->autonomia()->configurazione($u,(int)$r->parametro('id')); }
 public function configura(Request $r,array $u): array { return $this->c->autonomia()->configura($u,(int)$r->parametro('id'),$r->corpo()); }
 public function chiusura(Request $r,array $u): array { return $this->c->autonomia()->chiusura($u,(int)$r->parametro('id'),$r->corpo()); }
 public function modificaChiusura(Request $r,array $u): array { return $this->c->autonomia()->modificaChiusura($u,(int)$r->parametro('id'),(int)$r->parametro('periodo'),$r->corpo()); }
 public function eliminaChiusura(Request $r,array $u): array { return $this->c->autonomia()->modificaChiusura($u,(int)$r->parametro('id'),(int)$r->parametro('periodo'),$r->corpo(),true); }
 public function personale(Request $r,array $u): array { return $this->c->autonomia()->personale($u,(int)$r->parametro('id'),(string)($r->query('data')??date('Y-m-d'))); }
 public function regola(Request $r,array $u): array { return $this->c->autonomia()->regola($u,(int)$r->parametro('id'),$r->corpo()); }
 public function giorno(Request $r,array $u): array { return $this->c->autonomia()->correggiGiorno($u,(int)$r->parametro('id'),$r->corpo()); }
 public function report(Request $r,array $u): array { return $this->c->autonomia()->report($u,(string)($r->query('dal')??date('Y-m-01')),(string)($r->query('al')??date('Y-m-d')),$r->query('plesso_id')?(int)$r->query('plesso_id'):null,(string)($r->query('ordine')??'')); }
 public function solleciti(Request $r,array $u): array { $v=[];foreach(['dal','al','plesso_id','q'] as $k) if($r->query($k)!==null) $v[$k]=$r->query($k);return $this->c->autonomia()->solleciti($u,$v); }
}
