<?php
class PDF extends FPDF
{
    // variables
    public $cat;
    public $category;
    public $newPage;

    function Header()
    {
        // title => get year and test
        global $date_rmr, $test_name;

        // logo
        $this->Cell(20, 14, '', 1, 0, '');
        $this->Image('../img/logo_color.png', 19, 10, 16, 0);

        // title cell
        $this->setFont('', '');
        $this->Cell(140, 14, '', 1, 1);
        $this->SetXY(37, 12);
		$this->Cell(140, 0, txtconv("Rallye mathématique romand " . $date_rmr), 0, 0, 'C');
        $this->SetXY(37, 18);
		$this->Cell(140, 0, txtconv("Résultats de la " . $test_name), 0, 0, 'C');

        // last cell (containing category)
        $this->SetXY(177, 8);
        $this->Cell(20,14, txtconv($this->category), 1, 0, 'C');

        // go to line
        $this->SetY(30);

        return;
    }


    function AcceptPageBreak() {
        //tableHeader();
        $this->newPage = true;
        return false;
    }
}