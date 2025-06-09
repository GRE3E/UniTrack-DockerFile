import { Component, OnInit } from '@angular/core';
import { UserService } from 'src/app/services/user.service';
import { NavController } from '@ionic/angular';
import { EnvioCorreoService } from '../services/envio-correo.service';
import { ModalController } from '@ionic/angular'; //

@Component({
  selector: 'app-verificar',
  templateUrl: './verificar.page.html',
  styleUrls: ['./verificar.page.scss'],
})
export class VerificarPage implements OnInit {
  verificationCode: number | null= null ;
  constructor(
    private userService: EnvioCorreoService, 
    private navCtrl: NavController,
    private modalController: ModalController
  ) { }

  ngOnInit() {
  }
  verifyCode() {
    if (this.verificationCode) {
      this.userService.verifyVerificationCode(this.verificationCode).subscribe(
        (response: any) => {
          if (response.success) {
            // Guardar el id_user en el servicio para usarlo luego
            this.userService.currentUser = { id: response.id_user, code: this.verificationCode };
  
            // Navegar a la página de cambiar contraseña
            this.navCtrl.navigateForward('/contrasena');
          } else {
            alert('Código inválido o expirado');
          }
        },
        error => {
          console.error(error);
          alert('Error al verificar el código');
        }
      );
    } else {
      alert('Por favor, ingrese el código de verificación');
    }
  }

  async closeModal() {
    this.navCtrl.navigateRoot('/login'); 
  }

}
