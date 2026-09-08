<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class MenuGiornalieroRepository extends Repository
{
    /** Un record per combinazione plesso+tipologia+data (aggiornato, non duplicato). */
    public function salva(int $plessoId, int $tipologiaId, string $data, ?string $descrizione, ?string $fotoPath, ?int $utenteId): int
    {
        $esistente = $this->uno(
            'SELECT * FROM menu_giornalieri WHERE plesso_id = :p AND tipologia_menu_id = :t AND data = :d',
            ['p' => $plessoId, 't' => $tipologiaId, 'd' => $data]
        );

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
        if ($fotoPath !== null) {
            $dati['foto_path'] = $fotoPath; // la foto precedente si sostituisce solo se ne arriva una nuova
        }
        $this->aggiorna('menu_giornalieri', (int) $esistente['id'], $dati);
        return (int) $esistente['id'];
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
