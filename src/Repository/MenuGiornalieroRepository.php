<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class MenuGiornalieroRepository extends Repository
{
    /** Recupera soltanto la pubblicazione per la chiave operativa indicata. */
    public function trovaPerChiave(int $plessoId, int $tipologiaId, string $data): ?array
    {
        return $this->uno(
            'SELECT * FROM menu_giornalieri WHERE plesso_id = :p AND tipologia_menu_id = :t AND data = :d',
            ['p' => $plessoId, 't' => $tipologiaId, 'd' => $data]
        );
    }

    /** Un record per combinazione plesso+tipologia+data (aggiornato, non duplicato). */
    public function salva(int $plessoId, int $tipologiaId, string $data, ?string $descrizione, ?string $fotoPath, ?int $utenteId, bool $rimuoviFoto = false): int
    {
        $esistente = $this->trovaPerChiave($plessoId, $tipologiaId, $data);

        if ($esistente === null) {
            return $this->inserisci('menu_giornalieri', [
                'plesso_id'         => $plessoId,
                'tipologia_menu_id' => $tipologiaId,
                'data'              => $data,
                'descrizione'       => $descrizione,
                'foto_path'         => $fotoPath,
                'utente_id'         => $utenteId,
            ]);
        }

        $dati = ['descrizione' => $descrizione, 'utente_id' => $utenteId];
        if ($rimuoviFoto) {
            $dati['foto_path'] = null; // eliminazione esplicita, non avviene senza richiesta
        } elseif ($fotoPath !== null) {
            $dati['foto_path'] = $fotoPath; // sostituzione solo con una foto nuova
        }
        $this->aggiorna('menu_giornalieri', (int) $esistente['id'], $dati);
        return (int) $esistente['id'];
    }

    /** Elimina una sola eccezione, dopo i controlli di data/stato del servizio. */
    public function rimuoviPerChiave(int $plessoId, int $tipologiaId, string $data): bool
    {
        return $this->esegui(
            'DELETE FROM menu_giornalieri
             WHERE plesso_id = :p AND tipologia_menu_id = :t AND data = :d',
            ['p' => $plessoId, 't' => $tipologiaId, 'd' => $data]
        ) === 1;
    }

    /** Solo le eccezioni esplicite a partire da oggi (non tutti i modelli). */
    public function elencoProgrammati(string $dal): array
    {
        return $this->tutti(
            'SELECT m.*, p.nome AS plesso_nome, t.nome AS tipologia_nome
             FROM menu_giornalieri m
             JOIN plessi p ON p.id = m.plesso_id
             JOIN tipologie_menu t ON t.id = m.tipologia_menu_id
             WHERE m.data >= :dal
             ORDER BY m.data, p.nome, t.nome',
            ['dal' => $dal]
        );
    }

    /** Le pubblicazioni passate non vengono mai cancellate: restano consultabili. */
    public function elenco(string $data, ?int $plessoId = null): array
    {
        $sql = 'SELECT m.*, p.nome AS plesso_nome, t.nome AS tipologia_nome
                FROM menu_giornalieri m
                JOIN plessi p ON p.id = m.plesso_id
                JOIN tipologie_menu t ON t.id = m.tipologia_menu_id
                WHERE m.data = :d';
        $parametri = ['d' => $data];
        if ($plessoId !== null) {
            $sql .= ' AND m.plesso_id = :p';
            $parametri['p'] = $plessoId;
        }
        return $this->tutti($sql . ' ORDER BY p.nome, t.nome', $parametri);
    }
}
