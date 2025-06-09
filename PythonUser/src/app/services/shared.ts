import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class SharedService {
  private qrImageUrlSubject = new Subject<string>();
  qrImageUrl$ = this.qrImageUrlSubject.asObservable();

  setQrImageUrl(url: string) {
    this.qrImageUrlSubject.next(url);
  }
}
