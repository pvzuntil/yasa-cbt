<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Tes_hasil extends Member_Controller
{
	private $kode_menu = 'tes-hasil';
	private $kelompok = 'tes';
	private $url = 'manager/tes_hasil';

	function __construct()
	{
		parent::__construct();
		$this->load->model('cbt_user_model');
		$this->load->model('cbt_user_grup_model');
		$this->load->model('cbt_tes_model');
		$this->load->model('cbt_tes_token_model');
		$this->load->model('cbt_tes_topik_set_model');
		$this->load->model('cbt_tes_user_model');
		$this->load->model('cbt_tesgrup_model');
		$this->load->model('cbt_soal_model');
		$this->load->model('cbt_jawaban_model');
		$this->load->model('cbt_tes_soal_model');
		$this->load->model('cbt_tes_soal_jawaban_model');

		parent::cek_akses($this->kode_menu);
	}

	public function index($page = null, $id = null)
	{
		$data['kode_menu'] = $this->kode_menu;
		$data['url'] = $this->url;

		$tanggal_awal = date('Y-m-d H:i', strtotime('- 1 days'));
		$tanggal_akhir = date('Y-m-d H:i', strtotime('+ 1 days'));

		$data['rentang_waktu'] = $tanggal_awal . ' - ' . $tanggal_akhir;

		$query_group = $this->cbt_user_grup_model->get_group();
		$select = '<option value="semua">Semua Group</option>';
		if ($query_group->num_rows() > 0) {
			$query_group = $query_group->result();
			foreach ($query_group as $temp) {
				$select = $select . '<option value="' . $temp->grup_id . '">' . $temp->grup_nama . '</option>';
			}
		} else {
			$select = '<option value="0">Tidak Ada Group</option>';
		}
		$data['select_group'] = $select;

		$query_tes = $this->cbt_tes_user_model->get_by_group();
		$select = '<option value="semua">Semua Tes</option>';
		if ($query_tes->num_rows() > 0) {
			$query_tes = $query_tes->result();
			foreach ($query_tes as $temp) {
				$select = $select . '<option value="' . $temp->tes_id . '">' . $temp->tes_nama . '</option>';
			}
		}
		$data['select_tes'] = $select;

		$this->template->display_admin($this->kelompok . '/tes_hasil_view', 'Hasil Tes', $data);
	}

	/**
	 * Melakukan perubahan pada tes yang diseleksi
	 */
	function edit_tes()
	{
		$this->load->library('form_validation');

		$this->form_validation->set_rules('edit-testuser-id[]', 'Hasil Tes', 'required|strip_tags');
		$this->form_validation->set_rules('edit-pilihan', 'Pilihan', 'required|strip_tags');

		if ($this->form_validation->run() == TRUE) {
			$pilihan = $this->input->post('edit-pilihan', true);
			$tesuser_id = $this->input->post('edit-testuser-id', TRUE);

			if ($pilihan == 'hapus') {
				foreach ($tesuser_id as $kunci => $isi) {
					if ($isi == "on") {
						$this->cbt_tes_user_model->delete('tesuser_id', $kunci);
					}
				}
				$status['status'] = 1;
				$status['pesan'] = 'Hasil tes berhasil dihapus';
			} else if ($pilihan == 'hentikan') {
				foreach ($tesuser_id as $kunci => $isi) {
					if ($isi == "on") {
						$data_tes['tesuser_status'] = 4;
						$datenow = date('Y-m-d H:i:s');
						$data_tes['end_time'] = $datenow;

						$query_tes = $this->cbt_tes_user_model->get_by_kolom('tesuser_id', $kunci, 1)->row();
						$mulai = new DateTime($query_tes->tesuser_creation_time);
						$selesai = new DateTime($datenow);

						$intervalDate  = $selesai->diff($mulai);

						$data_tes['time_span'] = $intervalDate->i . "," . $intervalDate->s;

						$this->cbt_tes_user_model->update('tesuser_id', $kunci, $data_tes);
					}
				}
				$status['status'] = 1;
				$status['pesan'] = 'Tes berhasil dihentikan';
			} else if ($pilihan == 'buka') {
				foreach ($tesuser_id as $kunci => $isi) {
					if ($isi == "on") {
						$data_tes['tesuser_status'] = 1;
						$this->cbt_tes_user_model->update('tesuser_id', $kunci, $data_tes);
					}
				}
				$status['status'] = 1;
				$status['pesan'] = 'Tes berhasil dibuka, user bisa mengerjakan kembali';
			} else if ($pilihan == 'waktu') {
				foreach ($tesuser_id as $kunci => $isi) {
					if ($isi == "on") {
						$waktu = intval($this->input->post('waktu-menit', TRUE));

						$this->cbt_tes_user_model->update_menit($kunci, $waktu);
					}
				}
				$status['status'] = 1;
				$status['pesan'] = 'Waktu Tes berhasil ditambah';
			}
		} else {
			$status['status'] = 0;
			$status['pesan'] =
				array_values($this->form_validation->error_array())[0];
		}

		echo json_encode($status);
	}

	function export($tes_id = null, $status = null, $urutkan = null)
	{
		if (!empty($tes_id) and !empty($urutkan) and !empty($status)) {
			$this->load->library('excel');

			if ($status == 'mengerjakan') {
				$query = $this->cbt_tes_user_model->get_datatable(0, 0, $tes_id, $urutkan);
			} else {
				$query = $this->cbt_tes_user_model->get_datatable_hasiltes(0, 0, $tes_id, $urutkan);
			}

			$inputFileName = './public/form/form-data-hasil-tes.xls';
			$spreadsheet = IOFactory::load($inputFileName);
			$spreadsheet->setActiveSheetIndex(0);

			if ($query->num_rows() > 0) {
				$query = $query->result();
				$row = 2;
				foreach ($query as $temp) {
					$spreadsheet->setActiveSheetIndex(0)->setCellValue('A' . $row, ($row - 1));
					$spreadsheet->setActiveSheetIndex(0)->setCellValue('B' . $row, $temp->tesuser_creation_time);
					$spreadsheet->setActiveSheetIndex(0)->setCellValue('C' . $row, $temp->tes_nama ?? '');
					$spreadsheet->setActiveSheetIndex(0)->setCellValue('D' . $row, $temp->user_email);
					$spreadsheet->setActiveSheetIndex(0)->setCellValue('E' . $row, stripslashes($temp->user_firstname));
					$spreadsheet->setActiveSheetIndex(0)->setCellValue('F' . $row, $temp->kelas);
					$spreadsheet->setActiveSheetIndex(0)->setCellValue('G' . $row, $temp->nilai ?? 0);

					$row++;
				}
			}

			// Rename worksheet
			$spreadsheet->getActiveSheet()->setTitle('Report');

			// Set active sheet index to the first sheet, so Excel opens this as the first sheet
			$spreadsheet->setActiveSheetIndex(0);

			// Redirect output to a client’s web browser (Xlsx)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="Data Hasil Tes - ' . date('Y-m-d H-i') . '.xlsx"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');

			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
			$writer->save('php://output');
		}
	}

	function get_datatable()
	{
		// variable initialization
		$tes_id = $this->input->get('tes');
		$urutkan = $this->input->get('urutkan');
		$status = $this->input->get('status');

		$search = "";
		$start = 0;
		$rows = 10;

		// get search value (if any)
		if (isset($_GET['sSearch']) && $_GET['sSearch'] != "") {
			$search = $_GET['sSearch'];
		}

		// limit
		$start = $this->get_start();
		$rows = $this->get_rows();

		// run query to get user listing
		if ($status == 'mengerjakan') {
			$query = $this->cbt_tes_user_model->get_datatable($start, $rows, $tes_id, $urutkan);
			$iTotal = $this->cbt_tes_user_model->get_datatable_count($tes_id, $urutkan);
		} else {
			$query = $this->cbt_tes_user_model->get_datatable_hasiltes($start, $rows, $tes_id, $urutkan);
			$iTotal = $this->cbt_tes_user_model->get_datatable_hasiltes_count($tes_id, $urutkan);
		}
		
		$iFilteredTotal = $query->num_rows();
		
		// get result after running query and put it in array
		$i = $start;
		$query = $query->result();
		// $iTotal = count($query);

		$output = array(
			"sEcho" => intval($_GET['sEcho']),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach ($query as $temp) {
			$record = array();

			$record[] = ++$i;

			if (empty($temp->tesuser_creation_time)) {
				$record[] = 'Belum memulai tes';
			} else {
				$record[] = $temp->tesuser_creation_time;
			}

			if (empty($temp->end_time)) {
				if ($status == 'mengerjakan') {
					if ($temp->tesuser_status == 4) {
						$record[] = 'Kehabisan waktu (' . $temp->tes_duration_time . ' Menit 0 Detik)';
					} else {
						$record[] = 'Belum selesai tes';
					}
				} else {
					$record[] = '';
				}
			} else {
				$pecah = explode(',', $temp->time_span);
				$record[] = $temp->end_time . ' (' . $pecah[0] . ' Menit ' . $pecah[1] . ' Detik)';
			}

			if (empty($temp->tesuser_creation_time)) {
				$record[] = '0';
			} else {
				$record[] = $temp->tes_duration_time . ' menit';
			}

			$record[] = $temp->tes_nama ?? '';
			$record[] = '<span class="badge badge-lg badge-primary">Kelas ' . $temp->kelas . '</span>';
			// $record[] = $temp->grup_nama;
			if (empty($temp->tesuser_id)) {
				$record[] = '<b>' . stripslashes($temp->user_firstname) . '</b>';
			} else {
				$record[] = '<a href="#" title="Klik untuk mengetahui Detail Tes" onclick="detail_tes(\'' . $temp->tesuser_id . '\')"><b>' . stripslashes($temp->user_firstname) . '</b></a>';
			}
			if (empty($temp->nilai)) {
				$record[] = '0';
			} else {
				$record[] = $temp->nilai;
			}

			if (empty($temp->tesuser_status)) {
				$record[] = 'Belum memulai';
			} else {
				if ($temp->tesuser_status == 1) {
					$tanggal = new DateTime();
					// Cek apakah tes sudah melebihi batas waktu
					$tanggal_tes = new DateTime($temp->tesuser_creation_time);
					$tanggal_tes->modify('+' . $temp->tes_duration_time . ' minutes');
					if ($tanggal > $tanggal_tes) {
						$record[] = 'Selesai';
					} else {
						$tanggal = $tanggal_tes->diff($tanggal);
						$menit_sisa = ($tanggal->h * 60) + ($tanggal->i);
						$record[] = 'Berjalan (-' . $menit_sisa . ' menit)';
					}
				} else {
					$record[] = 'Selesai';
				}
			}

			// menampilkan pilihan edit untuk data yang sudah mengerjakan
			if (empty($temp->tesuser_id)) {
				$record[] = '';
			} else {
				$record[] = '<input type="checkbox" name="edit-testuser-id[' . $temp->tesuser_id . ']" >';
			}

			$output['aaData'][] = $record;
		}
		// format it to JSON, this output will be displayed in datatable

		echo json_encode($output);
	}

	/**
	 * funsi tambahan 
	 * 
	 * 
	 */

	function get_start()
	{
		$start = 0;
		if (isset($_GET['iDisplayStart'])) {
			$start = intval($_GET['iDisplayStart']);

			if ($start < 0)
				$start = 0;
		}

		return $start;
	}

	function get_rows()
	{
		$rows = 10;
		if (isset($_GET['iDisplayLength'])) {
			$rows = intval($_GET['iDisplayLength']);
			if ($rows < 5 || $rows > 500) {
				$rows = 10;
			}
		}

		return $rows;
	}

	function get_sort_dir()
	{
		$sort_dir = "ASC";
		$sdir = strip_tags($_GET['sSortDir_0']);
		if (isset($sdir)) {
			if ($sdir != "asc") {
				$sort_dir = "DESC";
			}
		}

		return $sort_dir;
	}
}
