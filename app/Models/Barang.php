<?php
class Barang {
    public $id;
    public $nama;
    public $kategori;
    public $stok;

    public function __construct($id, $nama, $kategori, $stok) {
        $this->id = $id;
        $this->nama = $nama;
        $this->kategori = $kategori;
        $this->stok = $stok;
    }
}
