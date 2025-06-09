import { Component, OnInit } from '@angular/core';
import { UserService } from '../services/user.service';
import { NavController } from '@ionic/angular';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { FlaskApiService } from '../services/flask-api.services.service';
import { ToastController } from '@ionic/angular';



@Component({
  selector: 'app-home',
  templateUrl: './home.page.html',
  styleUrls: ['./home.page.scss'],
})
export class HomePage implements OnInit {
  nombrecompleto: string = ''; 
  qrImageUrl: string=''; // URL de la imagen del QR
  isEntrada: boolean = true;
  toastMessage: string | null = null;
  id: number  | null=null; 
  nombre: string= ''; 
  correo: string= ''; 
  modo: string= ''; 
  correoA: string = ''; 
  currentUser: any; 

  constructor(private userService: UserService, private navCtrl: NavController, private http: HttpClient, private router: Router, private flask: FlaskApiService, private toastController: ToastController) { 
  }

  ngOnInit() {
    const user = localStorage.getItem('currentUser');
  this.currentUser = user ? JSON.parse(user) : null;
  console.log('Current User en ngOnInit:', this.currentUser);

  if (this.currentUser && this.currentUser.user && this.currentUser.user.nombres && this.currentUser.user.apellidos) {
    this.nombrecompleto = `${this.currentUser.user.nombres} ${this.currentUser.user.apellidos}`;
  } else {
    console.error('Propiedades de usuario no están definidas.');
  }
  }

  async generarQR() {
    if (!this.currentUser || !this.currentUser.user || !this.currentUser.user.idUsuario || !this.currentUser.user.nombres || !this.currentUser.user.correo || !this.currentUser.user.correoA) {
      console.error('Datos de usuario incompletos.');
      return;
    }
  
    this.id = this.currentUser.user.idUsuario;
    this.nombre = this.currentUser.user.nombres;
    this.correo = this.currentUser.user.correo;
    this.correoA = this.currentUser.user.correoA;
  
    if (this.id !== null) {
      this.modo = this.isEntrada ? 'Entrada' : 'Salida';
      try {
        // Realizar el reporte primero
        const reportResult = await this.flask.storeTempUser(this.id, this.nombre, this.correo, this.modo, this.correoA).toPromise();
        console.log('Datos enviados:', reportResult);
  
        // Si el reporte es exitoso, proceder con la generación del QR
        this.qrImageUrl = await this.flask.generarQR();
        console.log('QR Image URL:', this.qrImageUrl);
        this.navCtrl.navigateForward('/qr', {
          queryParams: { qrImageUrl: this.qrImageUrl }
        });
      } catch (error: unknown) {
        console.error('Error al enviar datos o generar QR:', error);
        const toast = await this.toastController.create({
          message: 'No puede generar el mismo modo de QR en un corto tiempo.',
          duration: 3000,
          position: 'bottom'
        });
        await toast.present();
      }
    }
  }

  async onToggleChange() {
    if (this.isEntrada) {
      this.toastMessage = 'Modo Entrada seleccionado';
    } else {
      this.toastMessage = 'Modo Salida seleccionado';
    } 
    const toast = await this.toastController.create({
      message: this.toastMessage,
      duration: 2000,
      position: 'bottom'
    });
    await toast.present();
  }

 
}