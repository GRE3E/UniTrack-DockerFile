import { Component, OnInit } from '@angular/core';
import { UserService } from '../services/user.service';
import { Router } from '@angular/router';
import { NavController,ToastController } from '@ionic/angular';
import { EnvioCorreoService } from '../services/envio-correo.service';

@Component({
  selector: 'app-correo',
  templateUrl: './correo.page.html',
  styleUrls: ['./correo.page.scss'],
})
export class CorreoPage implements OnInit {

  email: string = ''; 
  isSubmitting = false; // Para prevenir envío doble

  constructor(
    private userService: EnvioCorreoService,
    private router: Router,
    private navCtrl: NavController,
    private toastController: ToastController
  ) { }

  ngOnInit() {}

  async presentToast(message: string, color: string = 'danger') {
    const toast = await this.toastController.create({
      message,
      duration: 2000,
      color,
      position: 'bottom'
    });
    await toast.present();
  }
  
  sendVerificationCode() {
    if (this.isSubmitting) return;
    this.isSubmitting = true;

    // Validar campo requerido
    if (!this.email) {
      this.presentToast('El correo es obligatorio');
      this.isSubmitting = false;
      return;
    }

    // Validar formato de correo institucional
    const correoRegex = /^[a-zA-Z0-9._%+-]+@ucvvirtual\.edu\.pe$/;
    if (!correoRegex.test(this.email)) {
      this.presentToast('El correo debe ser institucional y válido');
      this.isSubmitting = false;
      return;
    }

    // Validar longitud máxima
    if (this.email.length > 100) {
      this.presentToast('El correo no debe superar 100 caracteres');
      this.isSubmitting = false;
      return;
    }
    
    this.userService.sendVerificationCodee(this.email).subscribe(
      response => {
        this.navCtrl.navigateForward('/verificar');
        this.isSubmitting = false;
      },
      error => {
        this.presentToast(error.message || 'Error al enviar el código de verificación');
        this.isSubmitting = false;
      }
    );
  }

}
