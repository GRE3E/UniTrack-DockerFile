import { Component, OnInit } from '@angular/core';
import { UserService } from 'src/app/services/user.service';
import { NavController,ToastController } from '@ionic/angular';
import { EnvioCorreoService } from '../services/envio-correo.service';

@Component({
  selector: 'app-verificar',
  templateUrl: './verificar.page.html',
  styleUrls: ['./verificar.page.scss'],
})
export class VerificarPage implements OnInit {

  verificationCode: number | null= null ;
  isSubmitting = false;

  constructor(
    private userService: EnvioCorreoService,
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

  verifyCode() {
    if (this.isSubmitting) return;
    this.isSubmitting = true;

    // Validar campo requerido
    if (this.verificationCode === null || this.verificationCode === undefined) {
      this.presentToast('Por favor, ingresa el código de verificación');
      this.isSubmitting = false;
      return;
    }

    // Validar que sea numérico y de 6 dígitos
    const codeStr = String(this.verificationCode);
    const codeRegex = /^\d{6}$/;
    if (!codeRegex.test(codeStr)) {
      this.presentToast('El código debe ser numérico de 6 dígitos');
      this.isSubmitting = false;
      return;
    }

    this.userService.verifyVerificationCode(Number(this.verificationCode)).subscribe(
  response => {
    if (response.success) {
      localStorage.setItem('reset_code', String(this.verificationCode));
      this.navCtrl.navigateForward('/contrasena');
    } else {
      this.presentToast(response.error || 'Código de verificación incorrecto');
    }
    this.isSubmitting = false;
  },
  error => {
    this.presentToast(error.message || 'Código de verificación incorrecto');
    this.isSubmitting = false;
  }
);
  }

}
