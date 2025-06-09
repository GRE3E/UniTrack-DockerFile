import { Component, OnInit } from '@angular/core';
import { FlaskService } from '../services/flask.service';
import { UserService } from '../services/user.service';
import { Reporte } from '../services/reporte';
import { ToastController } from '@ionic/angular';

@Component({
  selector: 'app-entradas',
  templateUrl: './entradas.page.html',
  styleUrls: ['./entradas.page.scss'],
})
export class EntradasPage implements OnInit {

  reportes: Reporte[] = []; // Utiliza la interfaz Reporte para definir el tipo de reportes
  verificationResult: string = '';
  nombrecompleto: string = ''; 
  

   constructor(
    private userService: UserService,
    private flaskservice: FlaskService,
    private toastController: ToastController
  ) {}

  async presentToast(message: string, color: string = 'danger') {
    const toast = await this.toastController.create({
      message,
      duration: 2000,
      color,
      position: 'bottom'
    });
    await toast.present();
  }

  loadReportes(): void {
    this.userService.getReportes().subscribe(
      data => {
        this.reportes = data;
      },
      error => {
        this.presentToast(error.message || 'Error al obtener los reportes');
      }
    );
  }

  ngOnInit() {
    this.loadReportes();

    const currentUser = this.userService.getCurrentUser();
    if (currentUser) {
      this.nombrecompleto = `${currentUser.nombres} ${currentUser.apellidos}`;
    }
  }

}
